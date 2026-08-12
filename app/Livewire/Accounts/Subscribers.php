<?php

namespace App\Livewire\Accounts;

use App\Models\Department;
use App\Models\Subscriber;
use App\Exports\SubscribersExport;
use App\Services\AccountFinalizer;
use Barryvdh\DomPDF\Facade\Pdf;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

/**
 * The Account Register list. ONE component drives three legacy screens, chosen by $mode:
 *   'all'       → View All Accounts        (read-only · status filter · Excel · PDF)
 *   'pending'   → Pending Issue Accounts   (Finalize · Delete · Excel · PDF)
 *   'finalized' → Finalized Issued Account (read-only · Excel · PDF)
 */
#[Layout('components.layouts.app')]
class Subscribers extends Component
{
    use WithPagination;

    /** Which screen this is: all | pending | finalized. Set from the route. */
    public string $mode = 'all';

    public int $perPage = 25;

    /** Free-text search over name + account number. */
    public string $search = '';

    /** The status filter — ONLY used on the "all" screen ('' = all · 'T' = pending · 'F' = finalized). */
    public string $status = '';

    /** IDs of the pending rows ticked for a batch action. */
    public array $selected = [];

    /** Each screen has its own legacy permission. */
    private const ABILITIES = [
        'all' => 'entrysection.view_all_accounts',
        'pending' => 'entrysection.pending_issue_accounts',
        'finalized' => 'entrysection.finalized_issued_account',
    ];

    /** Screen titles, reused by the header and the PDF. */
    private const TITLES = [
        'all' => 'View All Accounts',
        'pending' => 'Pending Issue Accounts',
        'finalized' => 'Finalized Issued Account',
    ];

    public function mount(string $mode = 'all'): void
    {
        $this->mode = $mode;
        $this->authorize(self::ABILITIES[$mode]);
    }

    /**
     * The save_flag this screen shows. Pending/Finalized are fixed by the route; only the
     * "all" screen lets the user narrow it with the status dropdown.
     */
    private function effectiveStatus(): string
    {
        return match ($this->mode) {
            'pending' => 'T',
            'finalized' => 'F',
            default => $this->status,
        };
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function export()
    {
        $this->authorize(self::ABILITIES[$this->mode]);

        return Excel::download(
            new SubscribersExport($this->search, $this->effectiveStatus()),
            'subscribers-' . $this->mode . '-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    public function pdf()
    {
        $this->authorize(self::ABILITIES[$this->mode]);

        $subscribers = Subscriber::query()
            ->with(['ddo', 'designationMaster', 'pran'])
            ->filter($this->search, $this->effectiveStatus())
            ->orderBy('id')
            ->get();

        $pdf = Pdf::loadView('pdf.subscribers', [
            'subscribers' => $subscribers,
            'departments' => Department::pluck('dept_name', 'dept_code'),
            'title' => self::TITLES[$this->mode] ?? self::TITLES['all'],
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        $filename = 'subscribers-' . $this->mode . '-' . now()->format('Y-m-d') . '.pdf';

        return response()->streamDownload(fn () => print($pdf->output()), $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function finalize(AccountFinalizer $finalizer): void
    {
        $this->authorize('entrysection.pending_issue_accounts');

        if (empty($this->selected)) {
            $this->dispatch('notify', type: 'error', message: 'Select at least one pending subscriber.');
            return;
        }

        $done = $finalizer->finalizeMany($this->selected);
        $this->selected = [];
        $this->dispatch('notify', type: 'success', message: 'Finalized ' . count($done) . ' subscriber(s).');
    }

    public function deleteSelected(): void
    {
        $this->authorize('entrysection.pending_issue_accounts');

        if (empty($this->selected)) {
            $this->dispatch('notify', type: 'error', message: 'Select at least one pending draft.');
            return;
        }

        // Only drafts (save_flag = 'T') can be deleted — a finalized account is never removed here.
        $deleted = Subscriber::where('save_flag', 'T')
            ->whereIn('id', $this->selected)
            ->delete();

        $this->selected = [];
        $this->dispatch('notify', type: 'success', message: 'Deleted ' . $deleted . ' pending draft(s).');
    }

    public function toggleSelectAll(): void
    {
        // Every row on a Pending page is a draft, so just take the whole page's ids.
        $pageIds = Subscriber::query()
            ->filter($this->search, 'T')
            ->orderBy('id')
            ->paginate($this->perPage)
            ->pluck('id')->map(fn ($id) => (string) $id)->all();

        $allSelected = count($pageIds) > 0 && count(array_diff($pageIds, $this->selected)) === 0;

        $this->selected = $allSelected
            ? array_values(array_diff($this->selected, $pageIds))
            : array_values(array_unique([...$this->selected, ...$pageIds]));
    }

    public function render()
    {
        $subscribers = Subscriber::query()
            ->with(['ddo', 'designationMaster', 'pran'])
            ->filter($this->search, $this->effectiveStatus())
            ->orderBy('id')
            ->paginate($this->perPage);

        return view('livewire.accounts.subscribers', [
            'subscribers' => $subscribers,
            'departments' => Department::pluck('dept_name', 'dept_code'),
            // On the Pending screen the whole page is pending, so these are all the page's ids.
            'pagePendingIds' => $this->mode === 'pending'
                ? $subscribers->pluck('id')->map(fn ($id) => (string) $id)->all()
                : [],
        ]);
    }
}
