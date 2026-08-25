<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * First Register — purpose wording + an "Others" option.
 *
 * 1. Relabel the 12 month purposes from "DEDUCTION FOR …" to "CONTRIBUTION FOR …".
 *    first_receipt stores the purpose CODE (D01…D12), never the words, so relabelling
 *    the master text leaves every existing receipt untouched — they simply read the
 *    new wording.
 * 2. Add a single "OTHERS" master row (pid = OTH). Ordering by pid puts it last.
 * 3. Add first_receipt.other_purpose to hold the free-text description typed when the
 *    operator picks OTHERS.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('purpose_master_codes')
            ->where('purpose', 'like', 'DEDUCTION%')
            ->update(['purpose' => DB::raw("REPLACE(purpose, 'DEDUCTION', 'CONTRIBUTION')")]);

        DB::table('purpose_master_codes')->updateOrInsert(['pid' => 'OTH'], ['purpose' => 'OTHERS']);

        Schema::table('first_receipt', function (Blueprint $table) {
            if (! Schema::hasColumn('first_receipt', 'other_purpose')) {
                $table->string('other_purpose', 150)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('first_receipt', function (Blueprint $table) {
            if (Schema::hasColumn('first_receipt', 'other_purpose')) {
                $table->dropColumn('other_purpose');
            }
        });

        DB::table('purpose_master_codes')->where('pid', 'OTH')->delete();

        DB::table('purpose_master_codes')
            ->where('purpose', 'like', 'CONTRIBUTION%')
            ->update(['purpose' => DB::raw("REPLACE(purpose, 'CONTRIBUTION', 'DEDUCTION')")]);
    }
};
