<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_permission', function (Blueprint $table) {
            // `user_id` maps to the legacy user_account.user_id (varchar). We deliberately do
            // NOT add a foreign key: user_account is a legacy table we don't own in Phase A,
            // and leaving it out keeps tests decoupled from the legacy schema.
            $table->string('user_id', 10);
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['user_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permission');
    }
};
