<?php

namespace App\Livewire\MasterData;

use App\Exports\DesignationsExport;
use App\Livewire\Concerns\WithCrudTable;
use App\Models\Designation;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Designation Master — the one master whose code is AUTO-GENERATED, so there is no
 * code field: on create the database assigns `designation_id`; on edit we change the
 * name and keep the id.
 */
#[Layout('components.layouts.app')]
class Designations extends Component
{
    use WithCrudTable;

    private const ABILITY = 'adminsection.designation_master';

    public string $search = '';

    public string $designation = '';

    /** Id being edited; null = creating a new designation. */
    public ?int $editingId = null;

    public function mount(): void
    {
        $this->authorize(self::ABILITY);
    }

    protected function rules(): array
    {
        return [
            // Names should be unique, ignoring the row we're editing.
            'designation' => [
                'required', 'string', 'max:180',
                Rule::unique('designation_master', 'designation')->ignore($this->editingId, 'designation_id'),
            ],
        ];
    }

    protected function messages(): array
    {
        return [
            'designation.required' => 'Enter a designation.',
            'designation.unique' => 'That designation already exists.',
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->reset(['designation', 'editingId']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $designation = Designation::findOrFail($id);

        $this->editingId = $designation->designation_id;
        $this->designation = $designation->designation;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize(self::ABILITY);

        $validated = $this->validate();

        if ($this->editingId) {
            // Update the existing row's name.
            Designation::whereKey($this->editingId)->update(['designation' => $validated['designation']]);
        } else {
            // Create — the database assigns the id.
            Designation::create(['designation' => $validated['designation']]);
        }

        $this->showForm = false;
        $this->reset(['designation', 'editingId']);
        $this->notify('Designation saved.');
    }

    public function delete(int $id): void
    {
        $this->authorize(self::ABILITY);

        Designation::whereKey($id)->delete();
        $this->notify('Designation deleted.');
    }

    public function export()
    {
        $this->authorize(self::ABILITY);

        return Excel::download(new DesignationsExport($this->search), 'designations-'.now()->format('Y-m-d').'.xlsx');
    }

    public function render()
    {
        $designations = Designation::query()
            ->search($this->search)
            ->orderBy('designation_id')
            ->paginate($this->perPage);

        return view('livewire.master-data.designations', compact('designations'));
    }
}
