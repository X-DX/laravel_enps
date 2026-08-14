<?php

namespace App\Livewire\Accounts;

use App\Models\Ddo;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Treasury;
use App\Models\Subscriber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Issue Account — the entry form for a NEW subscriber. Saves a DRAFT (save_flag = 'T');
 * the account number is generated later, at finalize (4d).
 */
#[Layout('components.layouts.app')]
class IssueAccount extends Component
{
    private const ABILITY = 'entrysection.issue_account';

    /* form fields */
    public string $name = '';
    public string $father_name = '';
    public string $mother_name = '';
    public bool $single_mother_flag = false; // stored as 0/1; a checkbox here

    public string $appnt_ord_no = '';    // appointment order
    public string $doapptorder = '';       // appointment date  (doapptorder)
    public string $dob = '';
    public string $doj = '';
    public string $dor = '';             // retirement date (auto-filled from DOB)

    public string $designation = '';     // designation id
    public string $nameofdept = '';       // department  (nameofdept)
    public string $pension_type = 'N';   // N = NPS (default), U = UPS
    public string $pay = '';

    public string $treasury_code = '';   // Treasury Location — used to pick the DDO
    public string $ddocode = '';         // the chosen DDO

    public string $name_nominee = '';
    public string $name_nominee2 = '';
    public string $name_nominee3 = '';

    public string $starting_month = '';
    public string $starting_fin_year = '';

    /* edit mode */
    public ?int $editingId = null;      // null = issuing a new account; set = editing this one
    public bool $isFinalized = false;   // a finalized account freezes Department + Pension Type
    public string $account_no = '';     // shown read-only when editing a finalized account

    /** Retirement age (from the retirement_year setting; usually 60). */
    private int $retirementAge = 60;

    public function mount(?Subscriber $subscriber = null): void
    {
        if ($subscriber?->exists) {
            $this->authorize('entrysection.edit_issued_account');
            $this->loadForEdit($subscriber);
        } else {
            $this->authorize(self::ABILITY);
        }

        $this->retirementAge = (int) (DB::table('retirement_year')->value('year') ?? 60);
    }

    /** Pre-fill every form field from an existing subscriber (edit mode). */
    private function loadForEdit(Subscriber $subscriber): void
    {
        $this->editingId = $subscriber->id;
        $this->isFinalized = $subscriber->save_flag === 'F';
        $this->account_no = (string) ($subscriber->account_no ?? '');

        $this->name = (string) $subscriber->name;
        $this->father_name = (string) $subscriber->father_name;
        $this->mother_name = (string) $subscriber->mother_name;
        $this->single_mother_flag = (bool) $subscriber->single_mother_flag;
        $this->appnt_ord_no = (string) $subscriber->appnt_ord_no;
        $this->doapptorder = $subscriber->doapptorder?->format('Y-m-d') ?? '';
        $this->dob = $subscriber->dob?->format('Y-m-d') ?? '';
        $this->doj = $subscriber->doj?->format('Y-m-d') ?? '';
        $this->dor = $subscriber->dor?->format('Y-m-d') ?? '';
        $this->designation = (string) $subscriber->designation;
        $this->nameofdept = trim((string) $subscriber->nameofdept);
        $this->pension_type = $subscriber->pension_type ?: 'N';
        $this->pay = (string) $subscriber->pay;
        $this->ddocode = (string) $subscriber->ddocode;
        $this->treasury_code = (string) ($subscriber->ddo?->treasury_code ?? '');
        $this->name_nominee = (string) $subscriber->name_nominee;
        $this->name_nominee2 = (string) $subscriber->name_nominee2;
        $this->name_nominee3 = (string) $subscriber->name_nominee3;
        $this->starting_month = (string) $subscriber->starting_month;
        $this->starting_fin_year = (string) $subscriber->starting_fin_year;
    }

    /** Changing the Treasury Location clears the (now-stale) DDO — its list refills below. */
    public function updatedTreasuryCode(): void
    {
        $this->ddocode = '';
    }

    /** Single mother ticked → clear the father's name (they can't have both). */
    public function updatedSingleMotherFlag(): void
    {
        if ($this->single_mother_flag) {
            $this->father_name = '';
        }
    }

    /** When DOB changes, auto-fill the retirement date: DOB + retirement age, end of month. */
    public function updatedDob(): void
    {
        if ($this->dob === '') {
            $this->dor = '';
            return;
        }

        try {
            $this->dor = Carbon::parse($this->dob)->addYears($this->retirementAge)->endOfMonth()->format('Y-m-d');
        } catch (\Exception $e) {
            // If the date can't be understood, just leave the retirement date alone.
        }
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:8', 'max:50'],
            'father_name' => [$this->single_mother_flag ? 'nullable' : 'required', 'string', 'max:255'],
            'mother_name' => ['nullable', 'string', 'max:255'],
            'appnt_ord_no' => ['required', 'string'],
            'doapptorder' => ['required', 'date'],
            'dob' => ['required', 'date'],
            'doj' => ['required', 'date'],
            'dor' => ['required', 'date'],
            'designation' => ['required', 'integer', 'exists:designation_master,designation_id'],
            'nameofdept' => ['required', 'string', 'exists:department,dept_code'],
            'pension_type' => ['required', 'in:N,U'],
            'pay' => ['required', 'integer', 'min:1'],
            'treasury_code' => [$this->editingId ? 'nullable' : 'required', 'string'],   // edit: only a filter helper, DDO is the real field
            'ddocode' => ['required', 'integer', 'exists:ddo_master,ddo_sl'],
            'name_nominee' => ['required', 'string'],
            'name_nominee2' => ['nullable', 'string'],
            'name_nominee3' => ['nullable', 'string'],
            'starting_month' => ['required'],
            'starting_fin_year' => ['required', 'integer'],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.min' => 'The name must be at least 8 characters.',
            'father_name.required' => 'Enter the father name (or tick Single Mother).',
            'treasury_code.required' => 'Select a treasury location.',
            'ddocode.required' => 'Select a DDO.',
            'name_nominee.required' => 'Enter the first nominee.',
        ];
    }

    public function save(): void
    {
        // Editing an existing account takes a different path (UPDATE, not INSERT).
        if ($this->editingId !== null) {
            $this->updateSubscriber();
            return;
        }

        $this->authorize(self::ABILITY);

        $this->validate();

        Subscriber::create([
            // typed by the operator
            'name' => $this->name,
            'father_name' => $this->single_mother_flag ? null : $this->father_name,
            'mother_name' => $this->mother_name ?: null,
            'single_mother_flag' => $this->single_mother_flag ? 1 : 0,
            'appnt_ord_no' => $this->appnt_ord_no,
            'doapptorder' => $this->doapptorder,
            'dob' => $this->dob,
            'doj' => $this->doj,
            'dor' => $this->dor,
            'designation' => (int) $this->designation,
            'nameofdept' => $this->nameofdept,
            'pension_type' => $this->pension_type,
            'pay' => (int) $this->pay,
            'ddocode' => (int) $this->ddocode,
            'name_nominee' => $this->name_nominee,
            'name_nominee2' => $this->name_nominee2 ?: null,
            'name_nominee3' => $this->name_nominee3 ?: null,
            'starting_month' => $this->starting_month,
            'starting_fin_year' => (int) $this->starting_fin_year,
            // set by the system (matches the legacy insert exactly)
            'save_flag' => 'T',
            'entry_date' => now()->toDateString(),
            'user_id' => auth()->id(),
            'flag_pt' => 'N',
            'closure_reason_id' => 0,
            'isactive' => true,
        ]);

        $this->reset();   // clear the form for the next entry
        $this->dispatch('notify', type: 'success', message: 'Subscriber saved as a draft.');
    }

    /** Update the edited subscriber. A finalized account freezes department + pension type. */
    private function updateSubscriber(): void
    {
        $this->authorize('entrysection.edit_issued_account');

        $subscriber = Subscriber::findOrFail($this->editingId);

        // A finalized account's number is derived from department + pension type, so those are
        // frozen — snap them back to the stored values before we validate or save.
        if ($this->isFinalized) {
            $this->nameofdept = trim((string) $subscriber->nameofdept);
            $this->pension_type = $subscriber->pension_type ?: 'N';
        }

        $this->validate();

        $subscriber->update([
            'name' => $this->name,
            'father_name' => $this->single_mother_flag ? null : $this->father_name,
            'mother_name' => $this->mother_name ?: null,
            'single_mother_flag' => $this->single_mother_flag ? 1 : 0,
            'appnt_ord_no' => $this->appnt_ord_no,
            'doapptorder' => $this->doapptorder,
            'dob' => $this->dob,
            'doj' => $this->doj,
            'dor' => $this->dor,
            'designation' => (int) $this->designation,
            'nameofdept' => $this->nameofdept,
            'pension_type' => $this->pension_type,
            'pay' => (int) $this->pay,
            'ddocode' => (int) $this->ddocode,
            'name_nominee' => $this->name_nominee,
            'name_nominee2' => $this->name_nominee2 ?: null,
            'name_nominee3' => $this->name_nominee3 ?: null,
            'starting_month' => $this->starting_month,
            'starting_fin_year' => (int) $this->starting_fin_year,
        ]);

        $this->dispatch('notify', type: 'success', message: 'Account updated.');
    }

    public function render()
    {
        // The cascade: the DDOs under the chosen Treasury Location.
        $ddos = $this->treasury_code !== ''
            ? Ddo::where('treasury_code', $this->treasury_code)->orderBy('ddo_name')->get(['ddo_sl', 'ddo_name', 'ddo_code'])
            : collect();

        // When editing, the account's own DDO may belong to a treasury that isn't linked yet —
        // keep it in the list so it still shows as the selected option.
        if ($this->ddocode !== '' && ! $ddos->contains(fn ($d) => (string) $d->ddo_sl === (string) $this->ddocode)) {
            $current = Ddo::find((int) $this->ddocode, ['ddo_sl', 'ddo_name', 'ddo_code']);
            if ($current) {
                $ddos = collect([$current])->concat($ddos);
            }
        }

        return view('livewire.accounts.issue-account', [
            'treasuries' => Treasury::orderBy('treasury_name')->get(['treasury_code', 'treasury_name']),
            'departments' => Department::orderBy('dept_name')->get(['dept_code', 'dept_name']),
            'designations' => Designation::whereNotNull('designation')->where('designation', '!=', '')
                ->orderBy('designation')->get(['designation_id', 'designation']),
            'ddos' => $ddos,
        ]);
    }
}
