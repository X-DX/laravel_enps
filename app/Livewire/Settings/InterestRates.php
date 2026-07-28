<?php

namespace App\Livewire\Settings;

use App\Models\InterestRate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Change Interest Rate — a small CRUD of the interest rate per financial year.
 * No delete: a past year's declared rate is a historical record, not something to erase.
 */
#[Layout('components.layouts.app')]
class InterestRates extends Component
{
    private const ABILITY = 'adminsection.change_interest_rate';

    public string $fin_year = '';

    public string $rate = '';

    /** Id being edited; null = adding a new financial year. */
    public ?int $editingId = null;

    public bool $showForm = false;

    public function mount(): void
    {
        $this->authorize(self::ABILITY);
    }

    protected function rules(): array
    {
        return [
            'fin_year' => [
                'required', 'string', 'max:10',
                Rule::unique('rate', 'fin_year')->ignore($this->editingId, 'id'),
            ],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    protected function messages(): array
    {
        return [
            'fin_year.required' => 'Enter a financial year (e.g. 2015-2016).',
            'fin_year.unique' => 'That financial year already has a rate.',
            'rate.required' => 'Enter a rate.',
            'rate.numeric' => 'The rate must be a number.',
        ];
    }

    public function create(): void
    {
        $this->reset(['fin_year', 'rate', 'editingId']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $row = InterestRate::findOrFail($id);

        $this->editingId = $row->id;
        $this->fin_year = $row->fin_year;
        $this->rate = (string) $row->rate;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize(self::ABILITY);

        $validated = $this->validate();

        if ($this->editingId) {
            InterestRate::whereKey($this->editingId)->update($validated);
        } else {
            InterestRate::create($validated);
        }

        $this->showForm = false;
        $this->reset(['fin_year', 'rate', 'editingId']);
        $this->dispatch('notify', type: 'success', message: 'Interest rate saved.');
    }

    public function render()
    {
        return view('livewire.settings.interest-rates', [
            'rates' => InterestRate::orderByDesc('fin_year')->get(),
        ]);
    }
}
