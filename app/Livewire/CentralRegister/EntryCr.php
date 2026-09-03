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

    public function generate(): void
    {
        $this->authorize(self::ABILITY);

        if (empty($this->selected)) {
            $this->dispatch('notify', type: 'error', message: 'Select at least one receipt.');
            return;
        }

        $attach = trim($this->attachReceiptNo);
        $count = 0;
        $receiptNo = DB::transaction(function () use ($attach, &$count) {
            // Only our own receipts still waiting at 'CR' (guards against stale ticks).
            $receipts = FirstReceipt::whereIn('sl_no', $this->selected)->where('flag', 'CR')->get();

            if ($receipts->isEmpty()) {
                return null;
            }
            $count = $receipts->count();

            // Lock the counter so two operators can't grab the same number at once.
            $counter = DB::table('counter_centralreg')->where('cunterid', 1)->lockForUpdate()->first();
            $crSlNo  = (int) $counter->centregno;

            if ($attach !== '') {
                // Reuse an existing receipt number that belongs to us.
                $isOurs = DB::table('central_reg')
                    ->where('receipt_no', $attach)
                    ->where('user_id', auth()->id())
                    ->exists();

                if (! $isOurs) {
                    return false;   // invalid attach → abort (nothing written yet)
                }

                $receiptNo = (int) $attach;
            } else {
                $receiptNo = (int) $counter->recept_no;   // auto: next new number
            }

            foreach ($receipts as $fr) {
                DB::table('central_reg')->insert([
                    'sl_no'               => $crSlNo,
                    'receipt_no'          => $receiptNo,
                    'first_receipt_sl_no' => $fr->sl_no,
                    'user_id'             => auth()->id(),
                    'flag_p'              => 'P',
                    'order_no'            => $fr->order_no,
                    'draft_no'            => $fr->draft_no,
                    'amount'              => $fr->amount,
                    'order_date'          => $fr->order_date?->format('Y-m-d'),
                    'draft_date'          => $fr->draft_date?->format('Y-m-d'),
                    'bank_name'           => $fr->bank ? trim($fr->bank->bank_name) . ', ' . trim($fr->bank->branch_name) : null,
                    'purpose'             => $fr->purpose,
                ]);

                DB::table('central_reg_entry_date')->insert([
                    'receipt_no'     => $receiptNo,
                    'draft_no'       => $fr->draft_no,
                    'cen_entry_date' => now()->toDateString(),
                    'cr_sl_no'       => $crSlNo,
                    'amount'         => $fr->amount,
                ]);

                $crSlNo++;
            }

            // Serial ALWAYS advances (one per receipt); the receipt number is consumed ONLY when auto.
            DB::table('counter_centralreg')->where('cunterid', 1)->update([
                'centregno' => $crSlNo,
                'recept_no' => $attach !== '' ? (int) $counter->recept_no : $receiptNo + 1,
            ]);

            FirstReceipt::whereIn('sl_no', $receipts->pluck('sl_no'))->where('flag', 'CR')->update(['flag' => 'FZ']);

            return $receiptNo;
        });

        if ($receiptNo === false) {
            $this->dispatch('notify', type: 'error', message: 'That Existing Receipt No is not valid / not yours.');
            return;
        }

        if ($receiptNo === null) {
            $this->dispatch('notify', type: 'error', message: 'Nothing to generate — those receipts are no longer pending.');
            return;
        }

        // $this->reset('selected', 'attachReceiptNo', 'attachInfo', 'attachValid');
        // $this->dispatch('notify', type: 'success', message: "CR generated. Receipt No: {$receiptNo}");

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
