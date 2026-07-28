<?php

namespace App\Livewire\Settings;

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Change Share Rate — the employee vs. government contribution split
 * (`mst_contribution_share`). One row (sl = 1); the "edit config" pattern again.
 *
 * These are the core NPS percentages: employee contributes emp_share%, government
 * adds govt_share% (legacy production: 10% + 14%).
 */
#[Layout('components.layouts.app')]
class ContributionShare extends Component
{
    private const ABILITY = 'adminsection.change_share_rate';

    public string $emp_share = '';

    public string $govt_share = '';

    public function mount(): void
    {
        $this->authorize(self::ABILITY);

        $row = DB::table('mst_contribution_share')->first();
        $this->emp_share = (string) ($row->emp_share ?? '');
        $this->govt_share = (string) ($row->govt_share ?? '');
    }

    protected function rules(): array
    {
        return [
            'emp_share' => ['required', 'integer', 'between:0,100'],
            'govt_share' => ['required', 'integer', 'between:0,100'],
        ];
    }

    protected function messages(): array
    {
        return [
            'emp_share.required' => 'Enter the employee share.',
            'govt_share.required' => 'Enter the government share.',
        ];
    }

    public function save(): void
    {
        $this->authorize(self::ABILITY);

        $validated = $this->validate();

        if (DB::table('mst_contribution_share')->exists()) {
            DB::table('mst_contribution_share')->update([
                'emp_share' => $validated['emp_share'],
                'govt_share' => $validated['govt_share'],
            ]);
        } else {
            DB::table('mst_contribution_share')->insert([
                'sl' => 1,
                'emp_share' => $validated['emp_share'],
                'govt_share' => $validated['govt_share'],
            ]);
        }

        $this->dispatch('notify', type: 'success', message: 'Contribution share updated.');
    }

    public function render()
    {
        return view('livewire.settings.contribution-share');
    }
}
