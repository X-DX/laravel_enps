<?php

namespace App\Livewire\Settings;

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Change Retirement Year — a single configuration value (`retirement_year.year`).
 *
 * This is the "edit config" pattern, not CRUD: there is exactly one row, so we load
 * it in mount() and update it on save (inserting only if somehow empty). The table has
 * no primary key, so we use the query builder rather than an Eloquent model.
 */
#[Layout('components.layouts.app')]
class RetirementYear extends Component
{
    private const ABILITY = 'adminsection.change_retirement_year';

    public string $year = '';

    public function mount(): void
    {
        $this->authorize(self::ABILITY);

        $this->year = (string) (DB::table('retirement_year')->value('year') ?? '');
    }

    protected function rules(): array
    {
        return ['year' => ['required', 'integer', 'between:40,80']];
    }

    protected function messages(): array
    {
        return [
            'year.required' => 'Enter the retirement age.',
            'year.between' => 'The retirement age must be between 40 and 80.',
        ];
    }

    public function save(): void
    {
        $this->authorize(self::ABILITY);

        $validated = $this->validate();

        if (DB::table('retirement_year')->exists()) {
            DB::table('retirement_year')->update(['year' => $validated['year']]);
        } else {
            DB::table('retirement_year')->insert(['year' => $validated['year']]);
        }

        $this->dispatch('notify', type: 'success', message: 'Retirement year updated.');
    }

    public function render()
    {
        return view('livewire.settings.retirement-year');
    }
}
