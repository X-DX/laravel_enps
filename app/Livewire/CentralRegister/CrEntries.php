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
 * Read-only. Each finalized row shows its CR Receipt No (from central_reg). Editing a still-
 * pending row reuses the First Register edit form (same underlying first_receipt record).
 */
#[Layout('components.layouts.app')]
class CrEntries extends Component
{
    use WithPagination;

    public string $mode = 'all';
    public int $perPage = 25;
    public string $search = '';
    public string $status = '';   // '' all · 'CR' pending · 'FZ' finalized (only on the 'all' screen)

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

    /**
     * The Central Register stage: first_receipts at flag CR (pending) and/or FZ (finalized).
     * Search matches Receipt No (sl_no) or the CR Receipt No (central_reg.receipt_no).
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
            ->when($this->search !== '', function (Builder $q) {
                $term = '%' . strtolower($this->search) . '%';
                $q->where(function (Builder $q) use ($term) {
                    $q->whereRaw('CAST(sl_no AS TEXT) LIKE ?', [$term])
                        ->orWhereHas('centralReg', fn (Builder $c) => $c->whereRaw('CAST(receipt_no AS TEXT) LIKE ?', [$term]));
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
            ->with(['ddo.treasury', 'ddo.location', 'bank', 'purposeCode', 'centralReg'])
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
            ->with(['ddo.treasury', 'ddo.location', 'bank', 'purposeCode', 'centralReg'])
            ->orderByDesc('sl_no')
            ->paginate($this->perPage);

        return view('livewire.central-register.cr-entries', [
            'entries' => $entries,
            'title' => self::TITLES[$this->mode] ?? self::TITLES['all'],
        ]);
    }
}
