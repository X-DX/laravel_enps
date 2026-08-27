<?php

namespace App\Livewire\CentralRegister;

use App\Exports\FirstEntriesExport;
use App\Models\FirstReceipt;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Entry CR — "CR Generation" (legacy menu 201).
 *
 * Stage 2 of the money-in pipeline. First Register hands over receipts sitting at
 * flag 'CR' ("Pending at CR Generation"). Here the operator gives each an official
 * Central Register receipt number and the receipt moves to flag 'FZ' ("CR Generated").
 *
 * Built flow-by-flow. This slice shows the pending list (search by Receipt No, select-all,
 * Excel/PDF); the generate() action is added in the next step.
 */
#[Layout('components.layouts.app')]
class EntryCr extends Component
{
    use WithPagination;

    private const ABILITY = 'entrysection.entry_cr';

    public int $perPage = 25;
    public string $search = '';   // searches by Receipt No (first_receipt.sl_no) only

    /** first_receipt sl_no's ticked for the batch (used by generate() in the next step). */
    public array $selected = [];

    public function mount(): void
    {
        $this->authorize(self::ABILITY);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    /**
     * The receipts waiting for CR generation: our own first_receipts at flag 'CR'.
     * Search is by Receipt No (sl_no) only, per the screen requirement.
     * (OwnedByUser already limits rows to the current user; admins see all.)
     */
    protected function baseQuery(): Builder
    {
        return FirstReceipt::query()
            ->where('flag', 'CR')
            ->when($this->search !== '', fn (Builder $q) => $q->whereRaw('CAST(sl_no AS TEXT) LIKE ?', ['%' . $this->search . '%']));
    }

    /** Tick / untick every row on the current page. */
    public function toggleSelectAll(): void
    {
        $pageKeys = $this->baseQuery()
            ->orderByDesc('sl_no')
            ->paginate($this->perPage)
            ->pluck('sl_no')->map(fn ($id) => (string) $id)->all();

        $allSelected = count($pageKeys) > 0 && count(array_diff($pageKeys, $this->selected)) === 0;

        $this->selected = $allSelected
            ? array_values(array_diff($this->selected, $pageKeys))
            : array_values(array_unique([...$this->selected, ...$pageKeys]));
    }

    public function export()
    {
        $this->authorize(self::ABILITY);

        return Excel::download(
            new FirstEntriesExport(query: $this->baseQuery()),
            'cr-pending-' . now()->format('Y-m-d') . '.xlsx',
        );
    }

    public function pdf()
    {
        $this->authorize(self::ABILITY);

        $rows = $this->baseQuery()
            ->with(['ddo.treasury', 'ddo.location', 'bank', 'purposeCode'])
            ->orderByDesc('sl_no')
            ->get();

        $pdf = Pdf::loadView('pdf.first-entries', [
            'rows' => $rows,
            'title' => 'Entry CR — Pending at CR Generation',
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        return response()->streamDownload(fn () => print($pdf->output()), 'cr-pending-' . now()->format('Y-m-d') . '.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function render()
    {
        $entries = $this->baseQuery()
            ->with(['ddo.treasury', 'ddo.location', 'bank', 'purposeCode'])
            ->orderByDesc('sl_no')
            ->paginate($this->perPage);

        return view('livewire.central-register.entry-cr', [
            'entries' => $entries,
            'pageKeys' => $entries->pluck('sl_no')->map(fn ($id) => (string) $id)->all(),
        ]);
    }
}
