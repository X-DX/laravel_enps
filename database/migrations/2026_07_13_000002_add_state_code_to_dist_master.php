<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Our FIRST real change to a legacy table (`dist_master` came from CodeIgniter/MySQL).
 *
 * Two deliberate safety choices:
 *  1. The column is NULLABLE. The existing 30 districts keep working with no state, and
 *     the old CI app (which only inserts dist_code + dist_name) never breaks. Admins fill
 *     the state in over time via the Edit screen (a "progressive backfill").
 *  2. We add a real FOREIGN KEY to state_master. We own state_master, so we can enforce
 *     integrity here — you can never store a state_code that doesn't exist. NULLs are
 *     exempt from the FK, so old rows are fine.
 *
 * Fully reversible: down() drops the FK and the column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dist_master', function (Blueprint $table) {
            $table->integer('state_code')->nullable()->after('dist_code');

            $table->foreign('state_code')
                ->references('state_code')
                ->on('state_master')
                ->nullOnDelete();   // if a state is ever removed, districts fall back to NULL
        });
    }

    public function down(): void
    {
        Schema::table('dist_master', function (Blueprint $table) {
            $table->dropForeign(['state_code']);
            $table->dropColumn('state_code');
        });
    }
};
