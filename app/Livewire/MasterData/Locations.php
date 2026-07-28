<?php

namespace App\Livewire\MasterData;

use App\Exports\LocationsExport;
use App\Livewire\Concerns\WithCrudTable;
use App\Models\District;
use App\Models\Location;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Location Master — the first master with a RELATIONSHIP: a location belongs to a
 * district, so the form has a district dropdown and we validate that the chosen
 * district actually exists.
 */
#[Layout('components.layouts.app')]
class Locations extends Component
{
    use WithCrudTable;

    private const ABILITY = 'adminsection.location_master';

    /** Filter the list to one district ('' = all districts). */
    public string $filterDistrict = '';

    /* ---- form fields ---- */
    public string $loc_code = '';

    public string $loc_name = '';

    /** The selected parent district. */
    public string $dist_code = '';

    public ?int $editingCode = null;

    public function mount(): void
    {
        $this->authorize(self::ABILITY);
    }

    protected function rules(): array
    {
        return [
            'loc_code' => [
                'required', 'integer', 'min:1',
                Rule::unique('loc_master', 'loc_code')->ignore($this->editingCode, 'loc_code'),
            ],
            'loc_name' => ['required', 'string', 'max:255'],
            // The new part: the chosen district must be a real row in dist_master.
            'dist_code' => ['required', 'integer', 'exists:dist_master,dist_code'],
        ];
    }

    protected function messages(): array
    {
        return [
            'loc_code.required' => 'Enter a location code.',
            'loc_code.unique' => 'That location code is already in use.',
            'loc_name.required' => 'Enter a location name.',
            'dist_code.required' => 'Select a district.',
            'dist_code.exists' => 'The selected district does not exist.',
        ];
    }

    public function updatingFilterDistrict(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->reset(['loc_code', 'loc_name', 'dist_code', 'editingCode']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $code): void
    {
        $location = Location::findOrFail($code);

        $this->editingCode = $location->loc_code;
        $this->loc_code = (string) $location->loc_code;
        $this->loc_name = $location->loc_name;
        $this->dist_code = (string) $location->dist_code;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize(self::ABILITY);

        $validated = $this->validate();

        Location::updateOrCreate(
            ['loc_code' => $validated['loc_code']],
            ['loc_name' => $validated['loc_name'], 'dist_code' => $validated['dist_code']],
        );

        $this->showForm = false;
        $this->reset(['loc_code', 'loc_name', 'dist_code', 'editingCode']);
        $this->notify('Location saved.');
    }

    public function delete(int $code): void
    {
        $this->authorize(self::ABILITY);

        // ddo_master.loc_code is varchar(10) in the legacy schema (a known type
        // mismatch), so we compare as a string. Refuse if any DDO references it.
        if (DB::table('ddo_master')->where('loc_code', (string) $code)->exists()) {
            $this->notify('This location has DDOs linked to it and cannot be deleted.', 'error');

            return;
        }

        Location::where('loc_code', $code)->delete();
        $this->notify('Location deleted.');
    }

    public function export()
    {
        $this->authorize(self::ABILITY);

        return Excel::download(new LocationsExport($this->filterDistrict), 'locations-'.now()->format('Y-m-d').'.xlsx');
    }

    public function render()
    {
        $locations = Location::query()
            ->with('district')            // eager-load to avoid N+1 when showing the district name
            ->when($this->filterDistrict !== '', fn ($q) => $q->where('dist_code', $this->filterDistrict))
            ->orderBy('loc_code')
            ->paginate($this->perPage);

        return view('livewire.master-data.locations', [
            'locations' => $locations,
            'districts' => District::orderBy('dist_name')->get(['dist_code', 'dist_name']),
        ]);
    }
}
