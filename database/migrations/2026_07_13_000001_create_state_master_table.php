<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A brand-new, additive reference table (Phase A safe — we own it end to end).
 *
 * `state_code` is the official Government-of-India LGD (Local Government Directory)
 * code, e.g. Arunachal Pradesh = 12, Manipur = 14. Because those numbers are fixed
 * and hand-known, the primary key is NOT auto-incrementing — we insert the real code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('state_master', function (Blueprint $table) {
            $table->integer('state_code')->primary();   // official LGD code, not auto-increment
            $table->string('state_name', 100);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('state_master');
    }
};
