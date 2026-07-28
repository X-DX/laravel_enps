<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Re-points DDOs from Location to Treasury — additively and non-destructively.
 *
 *  - We ADD a new `treasury_code` column and KEEP the legacy `loc_code` untouched, so the
 *    3,085 existing DDO→Location links are preserved (Phase A: never destroy legacy data).
 *  - The column is NULLABLE: all 3,085 existing DDOs start with no treasury and acquire one
 *    as they are edited (a progressive backfill). The old CI app, which never sets it, keeps
 *    working too.
 *  - A real FOREIGN KEY to treasury_master enforces integrity WITHOUT blocking those NULLs:
 *    a foreign key never checks NULL values, so "empty" is allowed but any non-NULL value
 *    must be a real treasury. varchar(10) on both sides so the types match.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ddo_master', function (Blueprint $table) {
            $table->string('treasury_code', 10)->nullable()->after('loc_code');

            $table->foreign('treasury_code')
                ->references('treasury_code')
                ->on('treasury_master')
                ->nullOnDelete();   // if a treasury is deleted, its DDOs fall back to NULL
        });
    }

    public function down(): void
    {
        Schema::table('ddo_master', function (Blueprint $table) {
            $table->dropForeign(['treasury_code']);
            $table->dropColumn('treasury_code');
        });
    }
};
