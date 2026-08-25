<?php

namespace App\Livewire;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The dashboard — at-a-glance analytics for the NPS back office. Every count is guarded by
 * Schema::hasTable so the page still renders (as zeros) where a table is absent, e.g. in the
 * auth feature tests that only check the route is reachable.
 */
#[Layout('components.layouts.app')]
class Dashboard extends Component
{
    private function count(string $table, ?callable $filter = null): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $query = $this->ownedTable($table);
        if ($filter) {
            $filter($query);
        }

        return $query->count();
    }

    /**
     * A query builder for $table narrowed to the current user's rows (admins see everyone's),
     * so the dashboard matches the per-user lists. Caller must have checked the table exists.
     */
    private function ownedTable(string $table): \Illuminate\Database\Query\Builder
    {
        $query = DB::table($table);

        $user = auth()->user();
        if ($user && ! $user->isAdmin() && Schema::hasColumn($table, 'user_id')) {
            $query->where($table.'.user_id', $user->getAuthIdentifier());
        }

        return $query;
    }

    public function render()
    {
        $subscribers = $this->count('allotment_accnt_no');
        $finalized = $this->count('allotment_accnt_no', fn ($q) => $q->where('save_flag', 'F'));
        $pending = $this->count('allotment_accnt_no', fn ($q) => $q->where('save_flag', 'T'));
        $closed = $this->count('allotment_accnt_no', fn ($q) => $q->where('isactive', false));
        $prans = $this->count('pran_no');
        $pendingPrans = $this->count('pran_no', fn ($q) => $q->where('save_flag', 'T'));
        $nps = $this->count('allotment_accnt_no', fn ($q) => $q->where('pension_type', 'N')->where('save_flag', 'F'));
        $ups = $this->count('allotment_accnt_no', fn ($q) => $q->where('pension_type', 'U')->where('save_flag', 'F'));

        // % of finalized accounts that already have a PRAN recorded.
        $pranCoverage = $finalized > 0 ? (int) round(min($prans, $finalized) / $finalized * 100) : 0;

        // Top departments by subscriber count → the bar chart.
        $topDepartments = [];
        if (Schema::hasTable('allotment_accnt_no') && Schema::hasTable('department')) {
            $topDepartments = $this->ownedTable('allotment_accnt_no')
                ->selectRaw('trim(nameofdept) as code, count(*) as total')
                ->whereNotNull('nameofdept')
                ->groupByRaw('trim(nameofdept)')
                ->orderByDesc('total')
                ->limit(6)
                ->get()
                ->map(fn ($row) => [
                    'label' => DB::table('department')->where('dept_code', $row->code)->value('dept_name') ?: $row->code,
                    'total' => (int) $row->total,
                ])
                ->all();
        }

        return view('dashboard', [
            'subscribers' => $subscribers,
            'finalized' => $finalized,
            'pending' => $pending,
            'closed' => $closed,
            'prans' => $prans,
            'pendingPrans' => $pendingPrans,
            'nps' => $nps,
            'ups' => $ups,
            'pranCoverage' => $pranCoverage,
            'departments' => $this->count('department'),
            'ddos' => $this->count('ddo_master'),
            'treasuries' => $this->count('treasury_master'),
            'topDepartments' => $topDepartments,
        ]);
    }
}
