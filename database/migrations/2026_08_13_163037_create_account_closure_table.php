<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * The closure register — one row per closed account (account_no is the PK, so an account can be
 * closed only once). It OWNS the closure detail; on allotment_accnt_no we only flip `isactive`
 * to false. Name / PRAN / department are NOT copied here — we join to the account for those
 * (single source of truth).
 *
 * closure_reason_id is bigInteger to match m_closure_reason.id (bigint); Postgres refuses an
 * int↔bigint foreign key. deduction_month/year + closing_date are nullable in the DB (a couple
 * of legacy-closed rows have no such data), but required in the form going forward.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('account_closure', function (Blueprint $table) {
            $table->string('account_no', 30)->primary(); // one closure per account
            $table->bigInteger('closure_reason_id');
            $table->date('closing_date')->nullable(); // operator-entered 
            $table->smallInteger('deduction_month')->nullable(); // 1-12
            $table->smallInteger('deduction_year')->nullable(); // eg: 2026
            $table->string('closed_by', 10)->nullable(); // user_id who closed it
            $table->timestamp('created_at')->nullable();

            $table->foreign('closure_reason_id')
                ->references('id')
                ->on('m_closure_reason'); // can't delete a reason still in use
        });

        // Backfill the few accounts already closed the legacy way (isactive=false + a real reason).
        // They predate the deduction fields, so those stay null.
        DB::table('account_closure')->insertUsing(
            ['account_no', 'closure_reason_id', 'closing_date', 'created_at'],
            DB::table('allotment_accnt_no')
                ->where('isactive', false)
                ->where('closure_reason_id', '>', 0)
                ->whereNotNull('account_no')
                ->selectRaw('account_no, closure_reason_id, closure_date, now()')
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_closure');
    }
};
