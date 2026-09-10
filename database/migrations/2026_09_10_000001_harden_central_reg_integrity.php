<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes a duplicate CR Number impossible at the database level.
 *
 * WHY THIS EXISTS
 * The legacy CR generation read counter_centralreg, inserted, then wrote the counter back —
 * three separate statements with no lock. Two operators clicking "Generate CR No" in the same
 * moment both read the same value, so both booked the same CR Number (a classic lost update).
 * A transaction does NOT prevent this: a plain SELECT is a non-locking snapshot read.
 *
 * The new EntryCr::generate() already fixes the application side with lockForUpdate(). This
 * migration adds the guarantee *behind* it — a PRIMARY KEY, so no future code path, script or
 * manual query can reintroduce a duplicate, plus the indexes every Central Register screen needs.
 *
 * Before the key can be added, the 146 duplicates already in the legacy data must be resolved.
 * Business decision taken: RENUMBER, never delete. Every Central Register row is preserved and
 * the later of each colliding pair is issued a fresh CR Number from the top of the counter.
 * The old → new mapping is written to central_reg_renumber_log so an auditor can always trace
 * where a number went. A statutory register must never silently lose an entry.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The renumbering below is Postgres-specific (it uses ctid to address rows in a table
        // that has no primary key yet). The test suite builds its schema by hand on SQLite.
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::create('central_reg_renumber_log', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->bigInteger('old_sl_no');
            $t->bigInteger('new_sl_no');
            $t->bigInteger('first_receipt_sl_no')->nullable();
            $t->bigInteger('receipt_no')->nullable();
            $t->string('reason', 100)->default('legacy counter race — duplicate CR No');
            $t->timestamp('renumbered_at')->useCurrent();
        });

        DB::transaction(function () {
            // Take the counter lock first, exactly as generate() does, so this migration cannot
            // collide with an operator generating a CR at the same moment.
            $counter = DB::table('counter_centralreg')->where('cunterid', 1)->lockForUpdate()->first();
            $next = (int) ($counter->centregno ?? 1);

            // Keep the earliest physical row of each colliding group; renumber the rest.
            // ctid is the physical row address — the only way to address these rows individually
            // while sl_no is still ambiguous. Updating one row does not move the others.
            $dupes = DB::select("
                SELECT ctid::text AS row_id, sl_no, first_receipt_sl_no, receipt_no
                FROM (
                    SELECT ctid, sl_no, first_receipt_sl_no, receipt_no,
                           row_number() OVER (PARTITION BY sl_no ORDER BY ctid) AS rn
                    FROM central_reg
                    WHERE sl_no IN (SELECT sl_no FROM central_reg GROUP BY sl_no HAVING count(*) > 1)
                ) ranked
                WHERE rn > 1
                ORDER BY sl_no, ctid
            ");

            foreach ($dupes as $row) {
                DB::insert(
                    'INSERT INTO central_reg_renumber_log (old_sl_no, new_sl_no, first_receipt_sl_no, receipt_no) VALUES (?, ?, ?, ?)',
                    [$row->sl_no, $next, $row->first_receipt_sl_no, $row->receipt_no]
                );

                DB::update('UPDATE central_reg SET sl_no = ? WHERE ctid = ?::tid', [$next, $row->row_id]);

                $next++;
            }

            if ($dupes !== []) {
                DB::table('counter_centralreg')->where('cunterid', 1)->update(['centregno' => $next]);
            }
        });

        // The guarantee. From here a duplicate CR Number is rejected by Postgres itself.
        DB::statement('ALTER TABLE central_reg ADD CONSTRAINT central_reg_pkey PRIMARY KEY (sl_no)');

        // Every CR screen resolves central_reg from first_receipt; the lists and the "attach to an
        // existing receipt" lookup filter on receipt_no. Both were full scans over ~358k rows.
        DB::statement('CREATE INDEX central_reg_first_receipt_sl_no_index ON central_reg (first_receipt_sl_no)');
        DB::statement('CREATE INDEX central_reg_receipt_no_index ON central_reg (receipt_no)');

        // NOT VALID enforces the link for every NEW row but does not re-check history. 18 legacy
        // rows point at drafts that no longer exist and 103,281 pre-migration rows have no link at
        // all; both are real history we were told not to touch. Validate later once they're triaged.
        DB::statement('
            ALTER TABLE central_reg
            ADD CONSTRAINT central_reg_first_receipt_sl_no_foreign
            FOREIGN KEY (first_receipt_sl_no) REFERENCES first_receipt (sl_no)
            ON DELETE RESTRICT NOT VALID
        ');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE central_reg DROP CONSTRAINT IF EXISTS central_reg_first_receipt_sl_no_foreign');
        DB::statement('DROP INDEX IF EXISTS central_reg_receipt_no_index');
        DB::statement('DROP INDEX IF EXISTS central_reg_first_receipt_sl_no_index');
        DB::statement('ALTER TABLE central_reg DROP CONSTRAINT IF EXISTS central_reg_pkey');

        // Put the original numbers back, newest first so no intermediate state collides.
        if (Schema::hasTable('central_reg_renumber_log')) {
            DB::transaction(function () {
                foreach (DB::table('central_reg_renumber_log')->orderByDesc('id')->get() as $log) {
                    DB::table('central_reg')->where('sl_no', $log->new_sl_no)->update(['sl_no' => $log->old_sl_no]);
                }
            });

            Schema::drop('central_reg_renumber_log');
        }
    }
};
