<?php

namespace App\Livewire;

use App\Models\Subscriber;
use App\Support\Navigation\SidebarMenu;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

/**
 * The ⌘K / Ctrl+K command palette — jump to any screen the user can reach, or search accounts
 * by name / number and go straight to them. Rendered once in the app layout.
 */
class CommandPalette extends Component
{
    public string $query = '';

    public function render()
    {
        $user = auth()->user();
        $q = trim($this->query);
        $lower = strtolower($q);

        // Screens the user can navigate to (Dashboard + every permitted sidebar item).
        $nav = collect([['label' => 'Dashboard', 'url' => route('dashboard'), 'icon' => 'grid']]);

        if ($user && Schema::hasTable('menu_items') && Schema::hasTable('permissions')) {
            foreach (app(SidebarMenu::class)->forUser($user) as $section) {
                foreach ($section['subs'] as $sub) {
                    foreach ($sub['items'] as $item) {
                        if (! empty($item['url'])) {
                            $nav->push(['label' => $item['label'], 'url' => $item['url'], 'icon' => $item['icon']]);
                        }
                    }
                }
            }
        }

        $navResults = ($q === ''
            ? $nav
            : $nav->filter(fn ($n) => str_contains(strtolower($n['label']), $lower)))
            ->take(7)->values();

        // Live account search — 2+ chars, permitted, table present.
        $accounts = collect();
        if (strlen($q) >= 2 && $user?->hasPermissionTo('entrysection.view_all_accounts') && Schema::hasTable('allotment_accnt_no')) {
            $term = '%' . $lower . '%';
            $accounts = Subscriber::query()
                ->where(function ($w) use ($term) {
                    $w->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(account_no) LIKE ?', [$term]);
                })
                ->orderBy('name')
                ->limit(6)
                ->get(['id', 'name', 'account_no', 'save_flag'])
                ->map(fn ($s) => [
                    'label' => $s->name,
                    'meta' => $s->save_flag === 'T' ? 'Pending draft' : $s->account_no,
                    'url' => route('accounts.show', $s->id),
                ]);
        }

        return view('livewire.command-palette', [
            'navResults' => $navResults,
            'accounts' => $accounts,
        ]);
    }
}
