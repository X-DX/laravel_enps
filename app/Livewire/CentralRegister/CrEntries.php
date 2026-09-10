<?php

namespace App\Livewire\CentralRegister;

use App\Exports\CrEntriesExport;
use App\Models\FirstReceipt;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

/**
 * The Central Register browse lists. ONE component drives three screens, chosen by $mode:
 *   'all'       → View All CR Entries   (flag CR or FZ · status filter · Excel · PDF)
 *   'pending'   → Pending CR Entries    (flag CR)
 *   'finalized' → Finalized CR Entries  (flag FZ)
 *
 * Read-only; the numbers are issued by EntryCr. Each finalized row carries THREE distinct
 * identifiers that the legacy screens kept apart and which must not be conflated:
 *
 *   First Receipt No  first_receipt.sl_no          the row id of the draft as it arrived
 *   CR No             central_reg.sl_no            its serial in the Central Register
 *   Receipt No        central_reg.receipt_no       the number on the acknowledgement handed to
 *                                                  the DDO — SHARED by every draft finalized in
 *                                                  the same batch (38,122 receipts over 358,646
 *                                                  register rows)
 *
 * Editing a still-pending row reuses the First Register edit form (same first_receipt record).
 */
#[Layout('components.layouts.app')]
class CrEntries extends Component
{
    use WithPagination;

    public string $mode = 'all';
    public int $perPage = 25;
    public string $search = '';
    public string $status = '';   // '' all · 'CR' pending · 'FZ' finalized (only on the 'all' screen)

    /** Entry-date range, on first_receipt.date_of_entry. Both blank = browse everything. */
    public string $fromDate = '';
    public string $toDate = '';

    private const ABILITIES = [
        'all' => 'entrysection.view_all_cr_entries',
        'pending' => 'entrysection.pending_cr_entries',
        'finalized' => 'entrysection.finalized_cr_entries',
    ];

    private const TITLES = [
        'all' => 'View All CR Entries',
        'pending' => 'Pending CR Entries',
        'finalized' => 'Finalized CR Entries',
    ];

    /** Loaded on every read path so the list, Excel and PDF all agree and none of them N+1. */
    private const EAGER = ['ddo.treasury', 'ddo.location', 'bank', 'purposeCode', 'centralRegs'];

    public function mount(string $mode = 'all'): void
    {
        $this->mode = $mode;
        $this->authorize(self::ABILITIES[$mode]);
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

    public function updatingFromDate(): void
    {
        $this->resetPage();
    }

    public function updatingToDate(): void
    {
        $this->resetPage();
    }

    /** Clear every filter back to the default "browse everything" view. */
    public function resetFilters(): void
    {
        $this->reset('search', 'status', 'fromDate', 'toDate');
        $this->resetPage();
    }

    /**
     * The Central Register stage: first_receipts at flag CR (pending) and/or FZ (finalized).
     *
     * Search accepts any of the three identifiers above. A digits-only term is matched as a
     * NUMBER against the integer columns, not as text: the old `CAST(sl_no AS TEXT) LIKE '%1%'`
     * could never use an index and scanned all 274k rows on every keystroke. Anything else is
     * treated as a draft or order number.
     */
    protected function baseQuery(): Builder
    {
        $flags = match ($this->mode) {
            'pending' => ['CR'],
            'finalized' => ['FZ'],
            default => in_array($this->status, ['CR', 'FZ'], true) ? [$this->status] : ['CR', 'FZ'],
        };

        return FirstReceipt::query()
            ->whereIn('flag', $flags)
            ->when($this->fromDate !== '', fn (Builder $q) => $q->whereDate('date_of_entry', '>=', $this->fromDate))
            ->when($this->toDate !== '', fn (Builder $q) => $q->whereDate('date_of_entry', '<=', $this->toDate))
            ->when(trim($this->search) !== '', function (Builder $q) {
                $term = trim($this->search);

                $q->where(function (Builder $q) use ($term) {
                    if (ctype_digit($term)) {
                        $number = (int) $term;

                        $q->where('sl_no', $number)                       // First Receipt No
                            ->orWhereHas('centralRegs', fn (Builder $c) => $c
                                ->where('sl_no', $number)                 // CR No
                                ->orWhere('receipt_no', $number));        // Receipt No
                    } else {
                        $like = '%' . strtolower($term) . '%';

                        $q->whereRaw('LOWER(draft_no) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(order_no) LIKE ?', [$like]);
                    }
                });
            });
    }

    public function export()
    {
        $this->authorize(self::ABILITIES[$this->mode]);

        return Excel::download(
            new CrEntriesExport($this->baseQuery()),
            'cr-' . $this->mode . '-' . now()->format('Y-m-d') . '.xlsx',
        );
    }

    public function pdf()
    {
        $this->authorize(self::ABILITIES[$this->mode]);

        $rows = $this->baseQuery()
            ->with(self::EAGER)
            ->orderByDesc('sl_no')
            ->get();

        $pdf = Pdf::loadView('pdf.cr-entries', [
            'rows' => $rows,
            'title' => self::TITLES[$this->mode] ?? self::TITLES['all'],
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        return response()->streamDownload(fn () => print($pdf->output()), 'cr-' . $this->mode . '-' . now()->format('Y-m-d') . '.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function render()
    {
        $entries = $this->baseQuery()
            ->with(self::EAGER)
            ->orderByDesc('sl_no')
            ->paginate($this->perPage);

        return view('livewire.central-register.cr-entries', [
            'entries' => $entries,
            'title' => self::TITLES[$this->mode] ?? self::TITLES['all'],
            'hasFilters' => $this->search !== '' || $this->status !== '' || $this->fromDate !== '' || $this->toDate !== '',
        ]);
    }
}
