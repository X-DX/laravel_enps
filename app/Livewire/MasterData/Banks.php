<?php

namespace App\Livewire\MasterData;

use App\Exports\BanksExport;
use App\Livewire\Concerns\WithCrudTable;
use App\Models\Bank;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Bank Master — same template as Districts, with three fields and a hand-typed code.
 */
#[Layout('components.layouts.app')]
class Banks extends Component
{
    use WithCrudTable;

    private const ABILITY = 'adminsection.bank_entry';

    public string $search = '';

    /* ---- form fields ---- */
    public string $bank_code = '';

    public string $bank_name = '';

    public string $branch_name = '';

    /** Code being edited; null = creating. */
    public ?int $editingCode = null;

    public function mount(): void
    {
        $this->authorize(self::ABILITY);
    }

    protected function rules(): array
    {
        return [
            'bank_code' => [
                'required', 'integer', 'min:1',
                Rule::unique('bank_master', 'bank_code')->ignore($this->editingCode, 'bank_code'),
            ],
            'bank_name' => ['required', 'string', 'max:30'],
            'branch_name' => ['required', 'string', 'max:30'],
        ];
    }

    protected function messages(): array
    {
        return [
            'bank_code.required' => 'Enter a bank code.',
            'bank_code.unique' => 'That bank code is already in use.',
            'bank_name.required' => 'Enter a bank name.',
            'branch_name.required' => 'Enter a branch name.',
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->reset(['bank_code', 'bank_name', 'branch_name', 'editingCode']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $code): void
    {
        $bank = Bank::findOrFail($code);

        $this->editingCode = $bank->bank_code;
        $this->bank_code = (string) $bank->bank_code;
        $this->bank_name = $bank->bank_name;
        $this->branch_name = $bank->branch_name;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize(self::ABILITY);

        $validated = $this->validate();

        Bank::updateOrCreate(
            ['bank_code' => $validated['bank_code']],
            ['bank_name' => $validated['bank_name'], 'branch_name' => $validated['branch_name']],
        );

        $this->showForm = false;
        $this->reset(['bank_code', 'bank_name', 'branch_name', 'editingCode']);
        $this->notify('Bank saved.');
    }

    public function delete(int $code): void
    {
        $this->authorize(self::ABILITY);

        Bank::where('bank_code', $code)->delete();
        $this->notify('Bank deleted.');
    }

    public function export()
    {
        $this->authorize(self::ABILITY);

        return Excel::download(new BanksExport($this->search), 'banks-'.now()->format('Y-m-d').'.xlsx');
    }

    public function render()
    {
        $banks = Bank::query()
            ->search($this->search)
            ->orderBy('bank_code')
            ->paginate($this->perPage);

        return view('livewire.master-data.banks', compact('banks'));
    }
}
