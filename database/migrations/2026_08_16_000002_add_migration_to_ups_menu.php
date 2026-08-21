<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Migration to UPS" (legacy menu 161) exists in the legacy code and its `ups_migration` table
 * is already present, but the menu_items row and its permission were never imported — so the
 * feature could never show in the sidebar. This adds both (idempotently).
 *
 * The sidebar is data-driven (menu_items ⋈ permissions on legacy_menu_id), so once these two
 * rows exist the item appears under Entry Section → Account Register for anyone with the
 * permission (admins bypass).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('menu_items')->updateOrInsert(
            ['menu_id' => 161],
            [
                'menu_name' => '',
                'menu_label' => 'Migration to UPS',
                'menu' => 'entrysection',
                'sub_menu' => 'accountregister',
            ],
        );

        DB::table('permissions')->updateOrInsert(
            ['key' => 'entrysection.migration_to_ups'],
            [
                'name' => 'Migration to UPS',
                'group' => 'entrysection',
                'legacy_menu_id' => 161,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('permissions')->where('key', 'entrysection.migration_to_ups')->delete();
        DB::table('menu_items')->where('menu_id', 161)->delete();
    }
};
