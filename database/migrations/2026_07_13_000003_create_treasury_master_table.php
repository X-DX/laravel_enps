<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A brand-new master table (there is NO treasury concept in the legacy app).
 *
 * Because we own it AND it starts empty, we can be strict from day one — the exact
 * opposite of the state_code case, where 30 legacy rows forced us to be nullable:
 *  - `dist_code` is NOT NULL and carries a real FOREIGN KEY to dist_master. A treasury
 *    can never exist without a valid district.
 *  - `treasury_code` is a hand-typed code like "01", "02", "11". It is DIGITS ONLY but
 *    stored as a STRING, not an integer, because the leading zero is meaningful ("01" must
 *    not collapse to 1). It is the PK and is NOT auto-incrementing.
 *
 * `dist_code` is bigInteger (not integer) on purpose: dist_master.dist_code is bigint,
 * and Postgres refuses a foreign key between integer and bigint columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('treasury_master', function (Blueprint $table) {
            $table->string('treasury_code', 10)->primary();   // hand-typed digit-string, e.g. "01"
            $table->bigInteger('dist_code');                  // NOT NULL — every treasury has a district
            $table->string('treasury_name', 150);

            $table->foreign('dist_code')
                ->references('dist_code')
                ->on('dist_master');   // RESTRICT: can't delete a district still referenced here
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_master');
    }
};
