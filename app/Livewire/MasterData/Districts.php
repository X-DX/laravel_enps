<?php

namespace App\Livewire\MasterData;

use App\Exports\DistrictsExport;
use App\Livewire\Concerns\WithCrudTable;
use App\Models\District;
use App\Models\State;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

/**
 * District Master — list + create + edit + delete, all in one reactive component.
 * This is the reusable template for the other master-data screens.
 */
#[Layout('components.layouts.app')]
class Districts extends Component
{
    use WithCrudTable; // pagination, per-page, showForm, notify()

    /** The permission key (from the legacy menu) required to manage districts. */
    private const ABILITY = 'adminsection.district_master';

    /** Free-text search over code + name. */
    public string $search = '';

    /* ---- form fields ---- */
    public string $dist_code = '';

    /**
     * The state this district belongs to. Defaults to '12' = Arunachal Pradesh, so a
     * NEW district is pre-selected to AP. On EDIT this is loaded from the row — which is
     * empty ('') for the 30 legacy districts until an admin picks a state.
     */
    public string $state_code = '12';

    public string $dist_name = '';

    /** Code being edited; null means we are creating a NEW district. */
    public ?int $editingCode = null;

    public function mount(): void
    {
        // Belt-and-braces: the route already has can: middleware, but we also
        // guard the component itself.
        $this->authorize(self::ABILITY);
    }

    /**
     * Validation rules. On CREATE the code must be unique; on EDIT the code is
     * fixed (read-only in the UI), so we ignore the row being edited.
     */
    protected function rules(): array
    {
        return [
            'dist_code' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('dist_master', 'dist_code')->ignore($this->editingCode, 'dist_code'),
            ],
            // Required on both create and edit — this is what forces the legacy rows to
            // acquire a state the first time they are edited. Must be a real state_master row.
            'state_code' => ['required', 'integer', 'exists:state_master,state_code'],
            'dist_name' => ['required', 'string', 'max:255'],
        ];
    }

    protected function messages(): array
    {
        return [
            'dist_code.required' => 'Enter a district code.',
            'dist_code.integer' => 'The district code must be a number.',
            'dist_code.unique' => 'That district code is already in use.',
            'state_code.required' => 'Select a state.',
            'state_code.exists' => 'The selected state does not exist.',
            'dist_name.required' => 'Enter a district name.',
        ];
    }

    /** When the search term changes, jump back to page 1. */
    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /** Open a blank form to add a district (state pre-selected to Arunachal Pradesh). */
    public function create(): void
    {
        // reset() restores declared defaults, so state_code goes back to '12' (AP).
        $this->reset(['dist_code', 'state_code', 'dist_name', 'editingCode']);
        $this->resetValidation();
        $this->showForm = true;
    }

    /** Open the form pre-filled to edit an existing district. */
    public function edit(int $code): void
    {
        $district = District::findOrFail($code);

        $this->editingCode = $district->dist_code;
        $this->dist_code = (string) $district->dist_code;
        // Empty ('') for legacy rows with no state yet → the dropdown shows the placeholder.
        $this->state_code = (string) ($district->state_code ?? '');
        $this->dist_name = $district->dist_name;
        $this->resetValidation();
        $this->showForm = true;
    }

    /** Create or update, depending on whether we are editing. */
    public function save(): void
    {
        $this->authorize(self::ABILITY);

        $validated = $this->validate();

        // updateOrCreate: find by the code, then set the name — one call handles
        // both "add" and "update" (the modern replacement for the legacy add_update flag).
        District::updateOrCreate(
            ['dist_code' => $validated['dist_code']],
            ['state_code' => $validated['state_code'], 'dist_name' => $validated['dist_name']],
        );

        $this->showForm = false;
        $this->reset(['dist_code', 'state_code', 'dist_name', 'editingCode']);

        $this->notify('District saved.');
    }

    /** Delete — but refuse if any location still points at this district. */
    public function delete(int $code): void
    {
        $this->authorize(self::ABILITY);

        $district = District::find($code);

        if ($district === null) {
            return;
        }

        // Uses the hasMany relationship now that the Location model exists.
        if ($district->locations()->exists()) {
            $this->notify('This district has locations linked to it and cannot be deleted.', 'error');

            return;
        }

        $district->delete();
        $this->notify('District deleted.');
    }

    /** Download the currently-filtered districts as an Excel file. */
    public function export()
    {
        $this->authorize(self::ABILITY);

        return Excel::download(
            new DistrictsExport($this->search),
            'districts-' . now()->format('Y-m-d') . '.xlsx',
        );
    }

    public function render()
    {
        $districts = District::query()
            ->with('state')               // eager-load to avoid N+1 when showing the state name
            ->search($this->search)
            ->orderBy('dist_code')
            ->paginate($this->perPage);

        return view('livewire.master-data.districts', [
            'districts' => $districts,
            'states' => State::orderBy('state_name')->get(['state_code', 'state_name']),
        ]);
    }
}
