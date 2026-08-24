<?php

namespace App\Livewire\FirstRegister;

use App\Models\Bank;
use App\Models\Ddo;
use App\Models\FirstReceipt;
use App\Models\Location;
use App\Models\Purpose;
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

    public string $locCode = '';          // helper — filters the DDO list; not stored
    public string $ddocode = '';
    public string $orderNo = '';
    public string $orderDate = '';
    public bool $isDraft = false;         // checkbox: draft (type 'D') vs receipt (type 'R')
    public string $draftNo = '';
    public string $draftDate = '';
    public string $amount = '';
    public string $contributionType = ''; // SC = single · DC = double
    public string $pensionType = 'N';     // N = NPS · U = UPS
    public string $drawBankCode = '';
    public string $purpose = '';          // purpose_master_codes.pid

    /** Revealed after a duplicate warning, so the operator can deliberately override. */
    public bool $showForceSave = false;

    public function mount(): void
    {
        $this->authorize(self::ABILITY);
    }

    /** Office Location changed → clear the (now-stale) DDO; its list refills below. */
    public function updatedLocCode(): void
    {
        $this->ddocode = '';
    }

    protected function rules(): array
    {
        return [
            'locCode' => ['required'],
            'ddocode' => ['required', 'integer', 'exists:ddo_master,ddo_sl'],
            'orderNo' => ['required', 'string'],
            'orderDate' => ['required', 'date'],
            'draftNo' => ['required', 'numeric'],
            'draftDate' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:1'],
            'contributionType' => ['required', 'in:SC,DC'],
            'pensionType' => ['required', 'in:N,U'],
            'drawBankCode' => ['required', 'exists:bank_master,bank_code'],
            'purpose' => ['required', 'exists:purpose_master_codes,pid'],
        ];
    }

    protected function messages(): array
    {
        return [
            'locCode.required' => 'Select an office location.',
            'ddocode.required' => 'Select a DDO.',
            'contributionType.required' => 'Select the contribution type.',
            'drawBankCode.required' => 'Select the draw bank.',
            'purpose.required' => 'Select a purpose.',
        ];
    }

    public function save(bool $force = false): void
    {
        $this->authorize(self::ABILITY);
        $this->validate();

        // Duplicate guard: same draft/receipt number + date — unless the operator forces it.
        $exists = FirstReceipt::where('draft_no', $this->draftNo)
            ->whereDate('draft_date', $this->draftDate)
            ->exists();

        if ($exists && ! $force) {
            $this->showForceSave = true;
            $this->dispatch('notify', type: 'error', message: 'A receipt/draft with this number and date already exists. Click “Save anyway” to override.');
            return;
        }

        FirstReceipt::create([
            'draft_no' => $this->draftNo,
            'draft_date' => $this->draftDate,
            'order_no' => strtoupper($this->orderNo),
            'order_date' => $this->orderDate,
            'amount' => $this->amount,
            'date_of_entry' => now()->toDateString(),
            'flag' => 'T',
            'ddocode' => (int) $this->ddocode,
            'type' => $this->isDraft ? 'D' : 'R',
            'draw_bank_code' => (int) $this->drawBankCode,
            'purpose' => $this->purpose,
            'contribution_type' => $this->contributionType,
            'pension_type' => $this->pensionType,
            'user_id' => auth()->id(),
        ]);

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
        return view('livewire.first-register.first-entry', [
            'locations' => Location::orderBy('loc_name')->get(['loc_code', 'loc_name']),
            'banks' => Bank::orderBy('bank_name')->get(['bank_code', 'bank_name', 'branch_name']),
            'purposes' => Purpose::orderBy('pid')->get(['pid', 'purpose']),
            'ddos' => $this->locCode !== ''
                ? Ddo::where('loc_code', $this->locCode)->orderBy('ddo_name')->get(['ddo_sl', 'ddo_name', 'ddo_code'])
                : collect(),
        ]);
    }
}
