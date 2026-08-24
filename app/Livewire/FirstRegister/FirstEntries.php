<?php

namespace App\Livewire\FirstRegister;

use App\Exports\FirstEntriesExport;
use App\Models\FirstReceipt;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

/**
 * The First Register list. ONE component drives three screens, chosen by $mode:
 *   'all'       → View All First Entries   (status filter · Excel · PDF)
 *   'pending'   → Pending First Entry       (+ finalize/delete/edit come in slice 6.1b)
 *   'finalized' → Finalized First Entry     (read-only)
 */
#[Layout('components.layouts.app')]
class FirstEntries extends Component
{
    use WithPagination;

    public string $mode = 'all';
    public int $perPage = 25;
    public string $search = '';
    public string $status = '';   // '' all · 'T' pending · 'F' finalized (only on the 'all' screen)

    /** sl_no's of the pending rows ticked for finalize/delete (pending screen only). */
    public array $selected = [];

    private const ABILITIES = [
        'all' => 'entrysection.view_all_first_entries',
        'pending' => 'entrysection.pending_first_entry',
        'finalized' => 'entrysection.finalized_first_entry',
    ];

    private const TITLES = [
        'all' => 'View All First Entries',
        'pending' => 'Pending First Entry',
        'finalized' => 'Finalized First Entry',
    ];

    public function mount(string $mode = 'all'): void
    {
        $this->mode = $mode;
        $this->authorize(self::ABILITIES[$mode]);
    }

    private function effectiveStatus(): string
    {
        return match ($this->mode) {
            'pending' => 'T',
            // Finalized holds both CR and FZ; the dropdown may narrow to one of them.
            'finalized' => in_array($this->status, ['CR', 'FZ'], true) ? $this->status : 'F',
            // View All: '' = every flag, or narrow to T / CR / FZ.
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
            new FirstEntriesExport($this->search, $this->effectiveStatus()),
            'first-entries-' . $this->mode . '-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    public function pdf()
    {
        $this->authorize(self::ABILITIES[$this->mode]);

        $rows = FirstReceipt::query()
            ->with(['ddo.treasury', 'ddo.location', 'bank', 'purposeCode'])
            ->filter($this->search, $this->effectiveStatus())
            ->orderByDesc('sl_no')
            ->get();

        $pdf = Pdf::loadView('pdf.first-entries', [
            'rows' => $rows,
            'title' => self::TITLES[$this->mode] ?? self::TITLES['all'],
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        return response()->streamDownload(fn () => print($pdf->output()), 'first-entries-' . $this->mode . '-' . now()->format('Y-m-d') . '.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /* ---- pending actions (6.1b) ---- */

    public function finalizeSelected(): void
    {
        $this->authorize('entrysection.pending_first_entry');

        if (empty($this->selected)) {
            $this->dispatch('notify', type: 'error', message: 'Select at least one pending entry.');
            return;
        }

        $count = DB::transaction(fn () => FirstReceipt::whereIn('sl_no', $this->selected)
            ->where('flag', 'T')
            ->update(['flag' => 'CR', 'finalize_date' => now()->toDateString()]));

        $this->selected = [];
        $this->dispatch('notify', type: 'success', message: 'Finalized ' . $count . ' entry(ies).');
    }

    public function deleteSelected(): void
    {
        $this->authorize('entrysection.pending_first_entry');

        if (empty($this->selected)) {
            $this->dispatch('notify', type: 'error', message: 'Select at least one pending entry.');
            return;
        }

        // Only drafts (flag='T') can be deleted — a finalized entry is never removed here.
        $count = FirstReceipt::whereIn('sl_no', $this->selected)->where('flag', 'T')->delete();

        $this->selected = [];
        $this->dispatch('notify', type: 'success', message: 'Deleted ' . $count . ' draft entry(ies).');
    }

    public function toggleSelectAll(): void
    {
        // Every row on a Pending page is a draft, so take the whole page's sl_no's.
        $pageKeys = FirstReceipt::query()
            ->filter($this->search, 'T')
            ->orderByDesc('sl_no')
            ->paginate($this->perPage)
            ->pluck('sl_no')->map(fn ($id) => (string) $id)->all();

        $allSelected = count($pageKeys) > 0 && count(array_diff($pageKeys, $this->selected)) === 0;

        $this->selected = $allSelected
            ? array_values(array_diff($this->selected, $pageKeys))
            : array_values(array_unique([...$this->selected, ...$pageKeys]));
    }

    public function render()
    {
        $entries = FirstReceipt::query()
            ->with(['ddo.treasury', 'ddo.location', 'bank', 'purposeCode'])
            ->filter($this->search, $this->effectiveStatus())
            ->orderByDesc('sl_no')
            ->paginate($this->perPage);

        return view('livewire.first-register.first-entries', [
            'entries' => $entries,
            'pagePendingKeys' => $this->mode === 'pending'
                ? $entries->pluck('sl_no')->map(fn ($id) => (string) $id)->all()
                : [],
        ]);
    }
}
