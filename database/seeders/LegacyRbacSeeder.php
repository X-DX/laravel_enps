<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A one-time data migration: builds the new RBAC tables from the legacy data.
 *
 *  - roles            ← distinct user_account.role_flag
 *  - permissions      ← one per menu_items row (keeps legacy_menu_id for the join)
 *  - user_permission  ← each user's menu_ids CSV, mapped via legacy_menu_id
 *
 * Idempotent: it clears and rebuilds, so it is safe to re-run.
 */
class LegacyRbacSeeder extends Seeder
{
    private const ROLE_NAMES = [
        'A' => 'Administrator',
        'S' => 'Staff',
        'U' => 'User',
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $this->clearExisting();
            $this->seedRoles();
            $legacyIdToPermissionId = $this->seedPermissions();
            $this->seedUserGrants($legacyIdToPermissionId);
        });

        $this->report();
    }

    private function clearExisting(): void
    {
        // Delete in FK-safe order (children before parents).
        DB::table('user_permission')->delete();
        DB::table('role_permission')->delete();
        DB::table('permissions')->delete();
        DB::table('roles')->delete();
    }

    private function seedRoles(): void
    {
        $now = now();

        $codes = DB::table('user_account')
            ->whereNotNull('role_flag')
            ->distinct()
            ->pluck('role_flag');

        foreach ($codes as $code) {
            $code = trim((string) $code);
            if ($code === '') {
                continue;
            }

            DB::table('roles')->insert([
                'code' => $code,
                'name' => self::ROLE_NAMES[$code] ?? "Role {$code}",
                'description' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @return array<int,int> legacy menu_id => new permission id
     */
    private function seedPermissions(): array
    {
        $now = now();
        $map = [];
        $usedKeys = [];

        foreach (DB::table('menu_items')->orderBy('menu_id')->get() as $item) {
            $group = trim((string) $item->menu);
            $label = trim((string) $item->menu_label);
            if ($label === '') {
                $label = "Menu {$item->menu_id}";
            }

            $base = ($group !== '' ? Str::slug($group, '_').'.' : '').Str::slug($label, '_');
            if ($base === '') {
                $base = 'menu_'.$item->menu_id;
            }

            // Guarantee uniqueness against the unique(key) constraint.
            $key = isset($usedKeys[$base]) ? $base.'_'.$item->menu_id : $base;
            $usedKeys[$key] = true;

            $id = DB::table('permissions')->insertGetId([
                'key' => $key,
                'name' => $label,
                'group' => $group !== '' ? $group : null,
                'legacy_menu_id' => $item->menu_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $map[(int) $item->menu_id] = $id;
        }

        return $map;
    }

    /**
     * @param  array<int,int>  $map  legacy menu_id => permission id
     */
    private function seedUserGrants(array $map): void
    {
        $rows = [];

        $users = DB::table('user_account')
            ->select('user_id', 'menu_ids')
            ->whereNotNull('menu_ids')
            ->where('menu_ids', '<>', '')
            ->get();

        foreach ($users as $user) {
            $seen = [];

            foreach (explode(',', $user->menu_ids) as $raw) {
                $menuId = (int) trim($raw);

                // Skip blanks, unknown menu ids (deleted menus), and duplicates.
                if ($menuId <= 0 || ! isset($map[$menuId]) || isset($seen[$menuId])) {
                    continue;
                }

                $seen[$menuId] = true;
                $rows[] = [
                    'user_id' => $user->user_id,
                    'permission_id' => $map[$menuId],
                ];
            }
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('user_permission')->insert($chunk);
        }
    }

    private function report(): void
    {
        $roles = DB::table('roles')->count();
        $perms = DB::table('permissions')->count();
        $grants = DB::table('user_permission')->count();
        $usersGranted = DB::table('user_permission')->distinct()->count('user_id');

        $this->command?->info("Seeded {$roles} roles, {$perms} permissions, {$grants} grants across {$usersGranted} users.");

        // Parity: each user's grant count must equal the number of DISTINCT, VALID
        // menu_ids they had (i.e. ones that still exist in menu_items).
        $validMenuIds = DB::table('menu_items')->pluck('menu_id')
            ->map(fn ($v) => (int) $v)->flip();

        $mismatches = 0;

        $users = DB::table('user_account')
            ->whereNotNull('menu_ids')->where('menu_ids', '<>', '')->get();

        foreach ($users as $user) {
            $expected = collect(explode(',', $user->menu_ids))
                ->map(fn ($v) => (int) trim($v))
                ->filter(fn ($v) => $v > 0 && $validMenuIds->has($v))
                ->unique()->count();

            $actual = DB::table('user_permission')->where('user_id', $user->user_id)->count();

            if ($expected !== $actual) {
                $mismatches++;
            }
        }

        $mismatches === 0
            ? $this->command?->info('Parity check passed: every user\'s grants match their valid menu_ids.')
            : $this->command?->warn("Parity check: {$mismatches} user(s) mismatched.");
    }
}
