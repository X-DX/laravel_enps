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
use Illuminate\Support\Facades\DB;


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

    public string $attachReceiptNo = '';   // optional: file this batch under an existing receipt no
    public ?string $attachInfo = null;      // the hint shown under the filed
    public bool $attachValid = false;        // whether the above receipt number is valid (exists in central_reg) or is the typed number a real receipt of ours?

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
            ->when($this->search !== '', fn(Builder $q) => $q->whereRaw('CAST(sl_no AS TEXT) LIKE ?', ['%' . $this->search . '%']));
    }

    /** Tick / untick every row on the current page. */
    public function toggleSelectAll(): void
    {
        $pageKeys = $this->baseQuery()
            ->orderByDesc('sl_no')
            ->paginate($this->perPage)
            ->pluck('sl_no')->map(fn($id) => (string) $id)->all();

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

        return response()->streamDownload(fn() => print($pdf->output()), 'cr-pending-' . now()->format('Y-m-d') . '.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    // Livewire magic hook — it runs automatically whenever the attachReceiptNo property changes
    public function updatedAttachReceiptNo(): void
    {
        $value = trim($this->attachReceiptNo);

        if ($value === '') {
            $this->attachInfo = null;
            $this->attachValid = false;
            return;
        }

        // count how many central register rows already carry this receipt no - and are ours.
        $count = DB::table('central_reg')
            ->where('receipt_no', $value)
            ->where('user_id', auth()->id())
            ->count();

        $this->attachValid = $count > 0;
        $this->attachInfo = $this->attachValid
            ? "{$count} existing record(s) found against Receipt No {$value}."
            : "Receipt No {$value} not found for you.";
    }

    /**
     * Book the ticked drafts into the Central Register.
     *
     * THE CONCURRENCY RULE, and why the lock is not optional:
     * the legacy code read counter_centralreg, inserted, then wrote the counter back — three
     * statements with no lock. Two operators pressing this button in the same moment both read
     * the same value and both booked the same CR No; 146 such duplicates are in the legacy data.
     * A transaction alone does NOT prevent it, because a plain SELECT is a non-locking snapshot
     * read. lockForUpdate() makes the second operator WAIT and then re-read the fresh number.
     * A PRIMARY KEY on central_reg.sl_no now backs this up at the database level.
     *
     * The lock is held until commit, so everything that does not need it is done first (validating
     * the attach number, loading the drafts with their banks) and the writes inside are batched.
     * Every extra query in here freezes every other operator in the office.
     */
    public function generate(): void
    {
        $this->authorize(self::ABILITY);

        if (empty($this->selected)) {
            $this->dispatch('notify', type: 'error', message: 'Select at least one receipt.');
            return;
        }

        $attach = trim($this->attachReceiptNo);

        // Validated BEFORE the transaction: it is a read-only check and must not hold the counter.
        if ($attach !== '') {
            $isOurs = DB::table('central_reg')
                ->where('receipt_no', $attach)
                ->where('user_id', auth()->id())
                ->exists();

            if (! $isOurs) {
                $this->dispatch('notify', type: 'error', message: 'That Existing Receipt No is not valid / not yours.');
                return;
            }
        }

        // Eager-load the bank here, outside the lock. Resolving $fr->bank inside the loop used to
        // fire one query per draft — 471 round trips on a big batch, all while blocking everyone.
        $receipts = FirstReceipt::with('bank')
            ->whereIn('sl_no', $this->selected)
            ->where('flag', 'CR')
            ->get();

        if ($receipts->isEmpty()) {
            $this->dispatch('notify', type: 'error', message: 'Nothing to generate — those receipts are no longer pending.');
            return;
        }

        $count = $receipts->count();

        $receiptNo = DB::transaction(function () use ($attach, $receipts) {
            // Serialise every operator here. B blocks until A commits, then re-reads the new value.
            $counter = DB::table('counter_centralreg')->where('cunterid', 1)->lockForUpdate()->first();

            $crSlNo = (int) $counter->centregno;
            $receiptNo = $attach !== '' ? (int) $attach : (int) $counter->recept_no;
            $today = now()->toDateString();

            $crRows = [];
            $dateRows = [];

            foreach ($receipts as $fr) {
                $crRows[] = [
                    'sl_no' => $crSlNo,
                    'receipt_no' => $receiptNo,
                    'first_receipt_sl_no' => $fr->sl_no,
                    'user_id' => auth()->id(),
                    'flag_p' => 'P',
                    'order_no' => $fr->order_no,
                    'draft_no' => $fr->draft_no,
                    'amount' => $fr->amount,
                    'order_date' => $fr->order_date?->format('Y-m-d'),
                    'draft_date' => $fr->draft_date?->format('Y-m-d'),
                    'bank_name' => $fr->bank ? trim($fr->bank->bank_name) . ', ' . trim($fr->bank->branch_name) : null,
                    'purpose' => $fr->purpose,
                ];

                $dateRows[] = [
                    'receipt_no' => $receiptNo,
                    'draft_no' => $fr->draft_no,
                    'cen_entry_date' => $today,
                    'cr_sl_no' => $crSlNo,
                    'amount' => $fr->amount,
                ];

                $crSlNo++;
            }

            // Chunked so a large batch stays within the driver's placeholder limit.
            foreach (array_chunk($crRows, 500) as $chunk) {
                DB::table('central_reg')->insert($chunk);
            }
            foreach (array_chunk($dateRows, 500) as $chunk) {
                DB::table('central_reg_entry_date')->insert($chunk);
            }

            // The CR serial ALWAYS advances, one per draft. The receipt number is consumed only
            // when a new one was issued — attaching to an existing receipt must not burn a number.
            DB::table('counter_centralreg')->where('cunterid', 1)->update([
                'centregno' => $crSlNo,
                'recept_no' => $attach !== '' ? (int) $counter->recept_no : $receiptNo + 1,
            ]);

            FirstReceipt::whereIn('sl_no', $receipts->pluck('sl_no'))->where('flag', 'CR')->update(['flag' => 'FZ']);

            return $receiptNo;
        });

        $message = $attach !== ''
            ? "Filed {$count} receipt(s) under existing Receipt No {$receiptNo}."
            : "Generated Receipt No {$receiptNo} for {$count} receipt(s).";

        $this->reset('selected', 'attachReceiptNo', 'attachInfo', 'attachValid');
        $this->dispatch('notify', type: 'success', message: $message);
    }

    public function render()
    {
        $entries = $this->baseQuery()
            ->with(['ddo.treasury', 'ddo.location', 'bank', 'purposeCode'])
            ->orderByDesc('sl_no')
            ->paginate($this->perPage);

        return view('livewire.central-register.entry-cr', [
            'entries' => $entries,
            'pageKeys' => $entries->pluck('sl_no')->map(fn($id) => (string) $id)->all(),
        ]);
    }
}
