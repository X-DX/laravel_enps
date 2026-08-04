<?php

namespace App\Support\Navigation;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Builds the sidebar as the legacy 3-level tree — Section → Sub-section → Item —
 * filtered to the items the given user may access. Admins (who pass every
 * permission check) therefore see the whole menu.
 *
 * The legacy display names live in staff_menu.php, not in the data, so the two
 * maps below reproduce them. Note several `menu` codes (export, reexport,
 * accountinterest, closebalance) display UNDER "Admin Section".
 */
class SidebarMenu
{
    /** menu code => display Section name. */
    private const SECTIONS = [
        'adminsection' => 'Admin Section',
        'export' => 'Export Data',
        'reexport' => 'Re Export Data',
        'accountinterest' => 'Admin Section',
        'closebalance' => 'Admin Section',
        'queriessection' => 'Queries Section',
        'reportsection' => 'Report Section',
        'entrysection' => 'Entry Section',
    ];

    /** "menu|sub_menu" => display Sub-section name. */
    private const SUBSECTIONS = [
        'adminsection|masterentry' => 'Master Entry',
        'adminsection|balancesheet' => 'Balance Sheet',
        'adminsection|user' => 'User',
        'adminsection|others' => 'Others',
        'adminsection|delcontribution' => 'Delete Contribution',
        'export|export' => 'Export Data',
        'reexport|reexport' => 'Re Export Data',
        'accountinterest|' => 'Account Interest',
        'closebalance|closebalance' => 'Close Balance',
        'queriessection|employees' => 'Employees',
        'queriessection|contribution' => 'Contribution Details',
        'queriessection|draftreceiptapp' => 'Draft/Receipt/Application',
        'queriessection|transit' => 'Transit',
        'reportsection|missingcont' => 'Missing Cont Report',
        'reportsection|audit' => 'Audit Report',
        'reportsection|subscribers' => 'Subscribers Report',
        'reportsection|contribution' => 'Contribution Report',
        'reportsection|printtransit' => 'Transit Report',
        'entrysection|missingcredit' => 'Missing Credit',
        'entrysection|accountregister' => 'Account Register',
        'entrysection|firstregister' => 'First Register',
        'entrysection|returndraft' => 'Return Draft',
        'entrysection|returnschedule' => 'Return Schedule',
        'entrysection|centralregister' => 'Central Register',
        'entrysection|empregister' => 'Employee Register',
        'entrysection|arrearregister' => 'Arrear Register',
    ];

    /** Permission key => route name, for items whose module already exists. */
    private const ROUTES = [
        'adminsection.add_update_user' => 'admin.permissions',
        'adminsection.district_master' => 'master.districts',
        'adminsection.bank_entry' => 'master.banks',
        'adminsection.designation_master' => 'master.designations',
        'adminsection.location_master' => 'master.locations',
        'adminsection.treasury_master' => 'master.treasuries',
        'adminsection.ddo_entry' => 'master.ddos',
        'adminsection.change_interest_rate' => 'settings.interest-rates',
        'adminsection.change_retirement_year' => 'settings.retirement-year',
        'adminsection.change_share_rate' => 'settings.contribution-share',

        'entrysection.view_all_accounts' => 'accounts.index',
    ];

    /**
     * @return Collection<int, array{title:string, open:bool, subs:Collection}>
     */
    public function forUser(User $user): Collection
    {
        // Defensive: some pages render where the RBAC tables aren't present
        // (e.g. unrelated tests). Return an empty menu rather than erroring.
        if (! Schema::hasTable('menu_items') || ! Schema::hasTable('permissions')) {
            return collect();
        }

        $rows = DB::table('menu_items as m')
            ->join('permissions as p', 'p.legacy_menu_id', '=', 'm.menu_id')
            ->orderBy('m.menu_id')
            ->get(['m.menu', 'm.sub_menu', 'm.menu_label', 'm.menu_id', 'p.key as permission_key']);

        $sections = [];

        foreach ($rows as $r) {
            if (! filled($r->menu) || ! $user->hasPermissionTo($r->permission_key)) {
                continue;
            }

            // Legacy menu/sub_menu are padded char(15); normalise before lookup.
            $menu = strtolower(trim((string) $r->menu));
            $sub = strtolower(trim((string) $r->sub_menu));

            $sectionTitle = self::SECTIONS[$menu] ?? Str::headline($menu);
            $subTitle = self::SUBSECTIONS["{$menu}|{$sub}"]
                ?? ($sub !== '' ? Str::headline($sub) : $sectionTitle);

            $routeName = self::ROUTES[$r->permission_key] ?? null;
            $hasRoute = $routeName !== null && Route::has($routeName);

            $sections[$sectionTitle]['title'] = $sectionTitle;
            $sections[$sectionTitle]['order'] = min($sections[$sectionTitle]['order'] ?? PHP_INT_MAX, $r->menu_id);
            $sections[$sectionTitle]['subs'][$subTitle]['title'] = $subTitle;
            $sections[$sectionTitle]['subs'][$subTitle]['order'] = min($sections[$sectionTitle]['subs'][$subTitle]['order'] ?? PHP_INT_MAX, $r->menu_id);
            $sections[$sectionTitle]['subs'][$subTitle]['items'][] = [
                'label' => trim((string) $r->menu_label),
                'url' => $hasRoute ? route($routeName) : null,
                'active' => $hasRoute && request()->routeIs($routeName),
                'permission' => $r->permission_key,
                'menu_id' => $r->menu_id,
            ];
        }

        return collect($sections)
            ->sortBy('order')
            ->map(function (array $section) {
                $subs = collect($section['subs'])
                    ->sortBy('order')
                    ->map(function (array $sub) {
                        $items = collect($sub['items'])->sortBy('menu_id')->values();

                        return [
                            'title' => $sub['title'],
                            'open' => $items->contains(fn($i) => $i['active']),
                            'items' => $items,
                        ];
                    })->values();

                return [
                    'title' => $section['title'],
                    'open' => $subs->contains(fn($s) => $s['open']),
                    'subs' => $subs,
                ];
            })
            ->values();
    }

    /**
     * Friendly Section title for a top-level `menu` code (reused by the admin screen).
     */
    public static function titleFor(string $group): string
    {
        return self::SECTIONS[strtolower(trim($group))] ?? Str::headline($group);
    }
}
