<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rename the two closure columns to the clearer business terms. They record the LAST month/year
 * a contribution was deducted for the account — "last contribution", not a generic "deduction".
 * A rename keeps the existing data (the 2 backfilled rows are untouched).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_closure', function (Blueprint $table) {
            $table->renameColumn('deduction_month', 'last_contribution_month');
            $table->renameColumn('deduction_year', 'last_contribution_year');
        });
    }

    public function down(): void
    {
        Schema::table('account_closure', function (Blueprint $table) {
            $table->renameColumn('last_contribution_month', 'deduction_month');
            $table->renameColumn('last_contribution_year', 'deduction_year');
        });
    }
};
