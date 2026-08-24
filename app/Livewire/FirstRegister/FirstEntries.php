<?php

namespace App\Livewire\FirstRegister;

use App\Exports\FirstEntriesExport;
use App\Models\FirstReceipt;
use Barryvdh\DomPDF\Facade\Pdf;
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
            new FirstEntriesExport($this->search, $this->effectiveStatus()),
            'first-entries-' . $this->mode . '-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    public function pdf()
    {
        $this->authorize(self::ABILITIES[$this->mode]);

        $rows = FirstReceipt::query()
            ->with(['ddo', 'bank', 'purposeCode'])
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

    public function render()
    {
        $entries = FirstReceipt::query()
            ->with(['ddo', 'bank', 'purposeCode'])
            ->filter($this->search, $this->effectiveStatus())
            ->orderByDesc('sl_no')
            ->paginate($this->perPage);

        return view('livewire.first-register.first-entries', [
            'entries' => $entries,
        ]);
    }
}
