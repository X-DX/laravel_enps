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

    /** Retirement age (from the retirement_year setting; usually 60). */
    private int $retirementAge = 60;

    public function mount(): void
    {
        $this->authorize(self::ABILITY);
        $this->retirementAge = (int) (DB::table('retirement_year')->value('year') ?? 60);
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
            'treasury_code' => ['required', 'string'],
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

    public function render()
    {
        return view('livewire.accounts.issue-account', [
            'treasuries' => Treasury::orderBy('treasury_name')->get(['treasury_code', 'treasury_name']),
            'departments' => Department::orderBy('dept_name')->get(['dept_code', 'dept_name']),
            'designations' => Designation::whereNotNull('designation')->where('designation', '!=', '')
                ->orderBy('designation')->get(['designation_id', 'designation']),

            // The cascade: only the DDOs under the chosen Treasury Location. 
            'ddos' => $this->treasury_code !== '' ? Ddo::where('treasury_code', $this->treasury_code)->orderBy('ddo_name')->get(['ddo_sl', 'ddo_name', 'ddo_code']) : collect(),
        ]);
    }
}
