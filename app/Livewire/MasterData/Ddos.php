<?php

namespace App\Livewire\MasterData;

use App\Exports\DdosExport;
use App\Livewire\Concerns\WithCrudTable;
use App\Models\Ddo;
use App\Models\District;
use App\Models\Treasury;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

/**
 * DDO Master — the biggest master (3,085 rows). A DDO belongs to a TREASURY, so both the
 * list filter and the add/edit form use a CASCADING District → Treasury dropdown.
 *
 * The legacy `loc_code` is left untouched on each row; we simply show/edit the treasury.
 * Existing DDOs start with no treasury and get one the first time they are edited.
 */
#[Layout('components.layouts.app')]
class Ddos extends Component
{
    use WithCrudTable;

    private const ABILITY = 'adminsection.ddo_entry';

    /* ---- cascading list filter ---- */
    public string $filterDistrict = '';

    public string $filterTreasury = '';

    /* ---- form fields (code is auto-generated, so there is no code field) ---- */
    public string $ddo_name = '';

    /** Drives the form's cascading Treasury dropdown (not saved directly). */
    public string $form_district = '';

    /** The treasury this DDO belongs to. */
    public string $treasury_code = '';

    /** The code of the DDO being edited; null = creating (code auto-assigned). */
    public ?int $editingCode = null;

    public function mount(): void
    {
        $this->authorize(self::ABILITY);
    }

    protected function rules(): array
    {
        return [
            'ddo_name' => ['required', 'string', 'max:150'],
            'treasury_code' => ['required', 'string', 'exists:treasury_master,treasury_code'],
        ];
    }

    protected function messages(): array
    {
        return [
            'ddo_name.required' => 'Enter a DDO name.',
            'treasury_code.required' => 'Select a treasury.',
            'treasury_code.exists' => 'The selected treasury does not exist.',
        ];
    }

    /** Changing the district resets the (now-stale) treasury filter. */
    public function updatingFilterDistrict(): void
    {
        $this->filterTreasury = '';
        $this->resetPage();
    }

    public function updatingFilterTreasury(): void
    {
        $this->resetPage();
    }

    /** Changing the form's district clears the (now-stale) treasury choice. */
    public function updatingFormDistrict(): void
    {
        $this->treasury_code = '';
    }

    public function create(): void
    {
        $this->reset(['ddo_name', 'form_district', 'treasury_code', 'editingCode']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $code): void
    {
        $ddo = Ddo::with('treasury')->findOrFail($code);

        $this->editingCode = $ddo->ddo_code;
        $this->ddo_name = $ddo->ddo_name;
        // Empty ('') for legacy DDOs with no treasury yet → the dropdowns show placeholders.
        $this->treasury_code = (string) ($ddo->treasury_code ?? '');
        // Pre-select the district so the cascading Treasury dropdown is populated.
        $this->form_district = (string) ($ddo->treasury?->dist_code ?? '');
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize(self::ABILITY);

        $validated = $this->validate();

        if ($this->editingCode) {
            // Update only name + treasury; loc_code is left as-is (legacy data preserved).
            Ddo::whereKey($this->editingCode)->update([
                'ddo_name' => $validated['ddo_name'],
                'treasury_code' => $validated['treasury_code'],
            ]);
        } else {
            // The database assigns ddo_code; new DDOs have no location (loc_code stays NULL).
            Ddo::create([
                'ddo_name' => $validated['ddo_name'],
                'treasury_code' => $validated['treasury_code'],
            ]);
        }

        $this->showForm = false;
        $this->reset(['ddo_name', 'form_district', 'treasury_code', 'editingCode']);
        $this->notify('DDO saved.');
    }

    public function delete(int $code): void
    {
        $this->authorize(self::ABILITY);

        // No cross-table guard: DDOs are referenced by the huge transactional tables
        // (accounts/subscribers), so that integrity belongs to Phase B foreign keys.
        Ddo::where('ddo_code', $code)->delete();
        $this->notify('DDO deleted.');
    }

    public function export()
    {
        $this->authorize(self::ABILITY);

        return Excel::download(new DdosExport($this->filterDistrict, $this->filterTreasury), 'ddos-'.now()->format('Y-m-d').'.xlsx');
    }

    public function render()
    {
        $ddos = Ddo::query()
            ->with('treasury.district')   // nested eager-load for the Treasury + District columns
            ->forTreasuryFilter($this->filterDistrict, $this->filterTreasury)
            ->orderBy('ddo_code')
            ->paginate($this->perPage);

        return view('livewire.master-data.ddos', [
            'ddos' => $ddos,
            'districts' => District::orderBy('dist_name')->get(['dist_code', 'dist_name']),
            // The dependent dropdown: only the chosen district's treasuries.
            'filterTreasuries' => $this->filterDistrict !== ''
                ? Treasury::where('dist_code', $this->filterDistrict)->orderBy('treasury_name')->get(['treasury_code', 'treasury_name'])
                : collect(),
            // The form's treasury picker cascades from the form's district selection.
            'formTreasuries' => $this->form_district !== ''
                ? Treasury::where('dist_code', $this->form_district)->orderBy('treasury_name')->get(['treasury_code', 'treasury_name'])
                : collect(),
        ]);
    }
}
