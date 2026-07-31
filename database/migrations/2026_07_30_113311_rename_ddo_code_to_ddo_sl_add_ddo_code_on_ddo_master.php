<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Rename the auto-increment serial. It STAYS the primary key - the sequence that generates its numbers follows the column automatically in Postgres.
        Schema::table('ddo_master', function (Blueprint $table) {
            $table->renameColumn('ddo_code', 'ddo_sl');
        });

        // 2. Add the real 7-digit DDO code. NULLABLE, because the 3,085 exisiting DDOs don't have one yet (they'll get it as they're edited). Then forbid the same code appearing twice within one treasury.
        Schema::table('ddo_master', function (Blueprint $table) {
            $table->string('ddo_code', 7)->nullable()->after('ddo_sl');
            $table->unique(['treasury_code', 'ddo_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Exact reverse order: undo the unique + new column first, then rename back.
        Schema::table('ddo_master', function (Blueprint $table) {
            $table->dropUnique(['treasury_code', 'ddo_code']);
            $table->dropColumn('ddo_code');
        });

        Schema::table('ddo_master', function (Blueprint $table) {
            $table->renameColumn('ddo_sl', 'ddo_code');
        });
    }
};
