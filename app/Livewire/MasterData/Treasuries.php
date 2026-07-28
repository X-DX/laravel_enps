<?php

namespace App\Livewire\MasterData;

use App\Exports\TreasuriesExport;
use App\Livewire\Concerns\WithCrudTable;
use App\Models\District;
use App\Models\Treasury;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Treasury Master — like Location Master (a treasury belongs to a district), but this
 * screen uses a free-text SEARCH BOX (over code + name) rather than a district filter.
 * The add/edit form still picks the parent district from a dropdown.
 */
#[Layout('components.layouts.app')]
class Treasuries extends Component
{
    use WithCrudTable;

    private const ABILITY = 'adminsection.treasury_master';

    /** Free-text search over code + name. */
    public string $search = '';

    /* ---- form fields ---- */
    public string $treasury_code = '';

    public string $treasury_name = '';

    /** The selected parent district. */
    public string $dist_code = '';

    /** The code being edited; string because codes like "01" must keep their leading zero. */
    public ?string $editingCode = null;

    public function mount(): void
    {
        $this->authorize(self::ABILITY);
    }

    protected function rules(): array
    {
        return [
            'treasury_code' => [
                // Digits only (e.g. "01"), stored as a string so the leading zero survives.
                'required', 'string', 'max:10', 'regex:/^[0-9]+$/',
                Rule::unique('treasury_master', 'treasury_code')->ignore($this->editingCode, 'treasury_code'),
            ],
            'treasury_name' => ['required', 'string', 'max:150'],
            'dist_code' => ['required', 'integer', 'exists:dist_master,dist_code'],
        ];
    }

    protected function messages(): array
    {
        return [
            'treasury_code.required' => 'Enter a treasury code.',
            'treasury_code.regex' => 'The treasury code must contain digits only (e.g. 01).',
            'treasury_code.unique' => 'That treasury code is already in use.',
            'treasury_name.required' => 'Enter a treasury name.',
            'dist_code.required' => 'Select a district.',
            'dist_code.exists' => 'The selected district does not exist.',
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->reset(['treasury_code', 'treasury_name', 'dist_code', 'editingCode']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(string $code): void
    {
        $treasury = Treasury::findOrFail($code);

        $this->editingCode = (string) $treasury->treasury_code;
        $this->treasury_code = (string) $treasury->treasury_code;
        $this->treasury_name = $treasury->treasury_name;
        $this->dist_code = (string) $treasury->dist_code;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize(self::ABILITY);

        $validated = $this->validate();

        Treasury::updateOrCreate(
            ['treasury_code' => $validated['treasury_code']],
            ['treasury_name' => $validated['treasury_name'], 'dist_code' => $validated['dist_code']],
        );

        $this->showForm = false;
        $this->reset(['treasury_code', 'treasury_name', 'dist_code', 'editingCode']);
        $this->notify('Treasury saved.');
    }

    public function delete(string $code): void
    {
        $this->authorize(self::ABILITY);

        // No cross-table guard yet: nothing references a treasury today. When the
        // transactional modules start pointing at it, integrity is the FK's job (Phase B).
        Treasury::where('treasury_code', $code)->delete();
        $this->notify('Treasury deleted.');
    }

    public function export()
    {
        $this->authorize(self::ABILITY);

        return Excel::download(new TreasuriesExport($this->search), 'treasuries-'.now()->format('Y-m-d').'.xlsx');
    }

    public function render()
    {
        $treasuries = Treasury::query()
            ->with('district')            // eager-load to avoid N+1 when showing the district name
            ->search($this->search)
            ->orderBy('treasury_code')
            ->paginate($this->perPage);

        return view('livewire.master-data.treasuries', [
            'treasuries' => $treasuries,
            'districts' => District::orderBy('dist_name')->get(['dist_code', 'dist_name']),
        ]);
    }
}
