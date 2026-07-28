<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();                                    // e.g. central_register.create
            $table->string('name');                                             // human-readable label
            $table->string('group')->nullable();                                // legacy top-level menu (entrysection, ...)
            $table->unsignedBigInteger('legacy_menu_id')->nullable()->index();  // maps menu_items.menu_id
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
