<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Registers the Treasury Master screen in the RBAC/menu system.
 *
 * This is the FIRST net-new screen with no legacy origin, so — unlike every other master —
 * there is no existing menu_items/permissions row to reuse. Our sidebar is data-driven
 * (it reads menu_items JOIN permissions), so to make Treasury appear we insert:
 *   1. a menu_items row  → tells the sidebar WHERE it lives (Admin Section → Master Entry)
 *   2. a permissions row → the ability the `can:` middleware and sidebar check
 *
 * Admins (role_flag 'A') bypass every permission check, so they see it immediately. Grant
 * it to other roles/users through the Manage User Permissions screen.
 *
 * Idempotent: keyed on menu_id / permission key, so re-running never duplicates.
 */
class TreasuryMasterMenuSeeder extends Seeder
{
    /** One past the legacy max menu_id (236), so Treasury sorts LAST within Master Entry. */
    private const MENU_ID = 237;

    public function run(): void
    {
        // 1. The sidebar location. `menu` / `sub_menu` are char(15) in the legacy schema;
        //    Postgres pads them and the sidebar trims, so the plain strings are fine.
        DB::table('menu_items')->updateOrInsert(
            ['menu_id' => self::MENU_ID],
            [
                'menu_name' => null,
                'menu_label' => 'Treasury Master',
                'menu' => 'adminsection',
                'sub_menu' => 'masterentry',
            ],
        );

        // 2. The ability, joined back to the menu row via legacy_menu_id.
        Permission::updateOrCreate(
            ['key' => 'adminsection.treasury_master'],
            ['name' => 'Treasury Master', 'group' => 'adminsection', 'legacy_menu_id' => self::MENU_ID],
        );
    }
}
