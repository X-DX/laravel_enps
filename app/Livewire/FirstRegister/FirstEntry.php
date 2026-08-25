<?php

namespace App\Livewire\FirstRegister;

use App\Models\Bank;
use App\Models\Ddo;
use App\Models\FirstReceipt;
use App\Models\Purpose;
use App\Models\Treasury;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Entry First Register (legacy menu 171) — record an incoming money receipt/draft. Office
 * Location filters the DDO list (cascade); everything else is typed/selected. Saves a row in
 * first_receipt with flag='T' (pending). A duplicate (same draft number + date) needs a
 * deliberate "Save anyway".
 */
#[Layout('components.layouts.app')]
class FirstEntry extends Component
{
    private const ABILITY = 'entrysection.entry_first_register';

    public string $treasuryCode = '';     // helper — filters the DDO list; not stored
    public string $ddocode = '';
    public string $orderNo = '';
    public string $orderDate = '';
    public bool $isDraft = false;         // checkbox: draft (type 'D') vs receipt (type 'R')
    public string $draftNo = '';
    public string $draftDate = '';
    public string $amount = '';
    public string $contributionType = ''; // SC = single · DC = double
    public string $pensionType = 'N';     // N = NPS · U = UPS
    public string $drawBankCode = '';     // only for drafts (a draft is drawn on a bank)
    public string $purpose = '';          // purpose_master_codes.pid
    public string $otherPurpose = '';     // free text, only when purpose = 'OTH'

    /** Revealed after a duplicate warning, so the operator can deliberately override. */
    public bool $showForceSave = false;

    /** null = a new entry; set = editing this receipt (sl_no). */
    public ?int $editingId = null;

    public function mount(?FirstReceipt $firstReceipt = null): void
    {
        $this->authorize(self::ABILITY);

        if ($firstReceipt?->exists) {
            $this->loadForEdit($firstReceipt);
        }
    }

    /** Pre-fill the form from an existing (pending) entry. */
    private function loadForEdit(FirstReceipt $r): void
    {
        $this->editingId = $r->sl_no;
        $this->ddocode = (string) $r->ddocode;
        $this->treasuryCode = (string) ($r->ddo?->treasury_code ?? '');
        $this->orderNo = (string) $r->order_no;
        $this->orderDate = $r->order_date?->format('Y-m-d') ?? '';
        $this->isDraft = $r->type === 'D';
        $this->draftNo = (string) $r->draft_no;
        $this->draftDate = $r->draft_date?->format('Y-m-d') ?? '';
        $this->amount = $r->amount !== null ? (string) (float) $r->amount : '';
        $this->contributionType = (string) $r->contribution_type;
        $this->pensionType = $r->pension_type ?: 'N';
        $this->drawBankCode = (string) $r->draw_bank_code;
        $this->purpose = (string) $r->purpose;
        $this->otherPurpose = (string) $r->other_purpose;
    }

    /** Treasury Location changed → clear the (now-stale) DDO; its list refills below. */
    public function updatedTreasuryCode(): void
    {
        $this->ddocode = '';
    }

    /** A Draft is a double contribution — auto-select it (and clear it back for a Receipt). */
    public function updatedIsDraft(): void
    {
        $this->contributionType = $this->isDraft ? 'DC' : '';

        // Draw bank only applies to a draft; drop it (and its error) when unticked.
        if (! $this->isDraft) {
            $this->drawBankCode = '';
            $this->resetErrorBag('drawBankCode');
        }
    }

    /** "OTHERS" reveals a free-text box; any other purpose clears it. */
    public function updatedPurpose(): void
    {
        if ($this->purpose !== 'OTH') {
            $this->otherPurpose = '';
            $this->resetErrorBag('otherPurpose');
        }
    }

    protected function rules(): array
    {
        return [
            'treasuryCode' => [$this->editingId ? 'nullable' : 'required'],
            'ddocode' => ['required', 'integer', 'exists:ddo_master,ddo_sl'],
            'orderNo' => ['required', 'string'],
            'orderDate' => ['required', 'date'],
            'draftNo' => ['required', 'numeric'],
            'draftDate' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:1'],
            'contributionType' => ['required', 'in:SC,DC'],
            'pensionType' => ['required', 'in:N,U'],
            // A bank only applies to a draft; required then, ignored for a receipt.
            'drawBankCode' => $this->isDraft ? ['required', 'exists:bank_master,bank_code'] : ['nullable'],
            'purpose' => ['required', 'exists:purpose_master_codes,pid'],
            // Free-text description required only when the purpose is "OTHERS".
            'otherPurpose' => $this->purpose === 'OTH' ? ['required', 'string', 'max:150'] : ['nullable'],
        ];
    }

    protected function messages(): array
    {
        return [
            'treasuryCode.required' => 'Select a treasury location.',
            'ddocode.required' => 'Select a DDO.',
            'contributionType.required' => 'Select the contribution type.',
            'drawBankCode.required' => 'Select the draw bank.',
            'purpose.required' => 'Select a purpose.',
            'otherPurpose.required' => 'Describe the purpose.',
        ];
    }

    public function save(bool $force = false): void
    {
        $this->authorize(self::ABILITY);
        $this->validate();

        // Duplicate guard: same draft/receipt number + date — excluding this record on edit.
        $duplicate = FirstReceipt::where('draft_no', $this->draftNo)
            ->whereDate('draft_date', $this->draftDate)
            ->when($this->editingId !== null, fn ($q) => $q->where('sl_no', '!=', $this->editingId))
            ->exists();

        if ($duplicate && ! $force) {
            $this->showForceSave = true;
            $this->dispatch('notify', type: 'error', message: 'A receipt/draft with this number and date already exists. Click “Save anyway” to override.');
            return;
        }

        $data = [
            'draft_no' => $this->draftNo,
            'draft_date' => $this->draftDate,
            'order_no' => strtoupper($this->orderNo),
            'order_date' => $this->orderDate,
            'amount' => $this->amount,
            'ddocode' => (int) $this->ddocode,
            'type' => $this->isDraft ? 'D' : 'R',
            'draw_bank_code' => $this->isDraft && $this->drawBankCode !== '' ? (int) $this->drawBankCode : null,
            'purpose' => $this->purpose,
            'other_purpose' => $this->purpose === 'OTH' ? $this->otherPurpose : null,
            'contribution_type' => $this->contributionType,
            'pension_type' => $this->pensionType,
        ];

        if ($this->editingId !== null) {
            FirstReceipt::where('sl_no', $this->editingId)->update($data);
            $this->showForceSave = false;
            $this->dispatch('notify', type: 'success', message: 'First register entry updated.');
            return;
        }

        FirstReceipt::create(array_merge($data, [
            'date_of_entry' => now()->toDateString(),
            'flag' => 'T',
            'user_id' => auth()->id(),
        ]));

        $this->reset();
        $this->pensionType = 'N';
        $this->dispatch('notify', type: 'success', message: 'First register entry saved as pending.');
    }

    public function forceSave(): void
    {
        $this->save(force: true);
    }

    public function render()
    {
        $ddos = $this->treasuryCode !== ''
            ? Ddo::where('treasury_code', $this->treasuryCode)->orderBy('ddo_name')->get(['ddo_sl', 'ddo_name', 'ddo_code'])
            : collect();

        // When editing, the entry's own DDO may belong to a treasury that isn't linked — keep it
        // in the list so it still shows as the selected option.
        if ($this->ddocode !== '' && ! $ddos->contains(fn ($d) => (string) $d->ddo_sl === (string) $this->ddocode)) {
            $current = Ddo::find((int) $this->ddocode, ['ddo_sl', 'ddo_name', 'ddo_code']);
            if ($current) {
                $ddos = collect([$current])->concat($ddos);
            }
        }

        return view('livewire.first-register.first-entry', [
            'treasuries' => Treasury::orderBy('treasury_name')->get(['treasury_code', 'treasury_name']),
            'banks' => Bank::orderBy('bank_name')->get(['bank_code', 'bank_name', 'branch_name']),
            'purposes' => Purpose::orderBy('pid')->get(['pid', 'purpose']),
            'ddos' => $ddos,
        ]);
    }
}
