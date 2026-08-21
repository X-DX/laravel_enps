<?php

namespace App\Livewire\Accounts;

use App\Models\Subscriber;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Migration to UPS (legacy menu 161). Search a finalized NPS account, then migrate it to the
 * Unified Pension Scheme: set pension_type='U' AND log the migration into ups_migration, in one
 * transaction. Only an NPS ('N') account can migrate; 'U' is already migrated.
 */
#[Layout('components.layouts.app')]
class MigrateToUps extends Component
{
    private const ABILITY = 'entrysection.migration_to_ups';

    public string $search = '';
    public string $selectedAccountNo = '';
    public string $migrationYear = '';
    public string $migrationMonth = '';

    public function mount(): void
    {
        $this->authorize(self::ABILITY);
    }

    public function selectAccount(string $accountNo): void
    {
        $account = Subscriber::where('account_no', $accountNo)
            ->where('save_flag', 'F')
            ->where('isactive', true)
            ->first();

        if ($account === null) {
            $this->dispatch('notify', type: 'error', message: 'Only a finalized, active account can be migrated.');
            return;
        }

        $this->selectedAccountNo = $accountNo;
        $this->search = '';
        $this->reset(['migrationYear', 'migrationMonth']);
    }

    /** Changing the year re-opens the month choice (the month list depends on the year). */
    public function updatedMigrationYear(): void
    {
        $this->migrationMonth = '';
    }

    public function closeForm(): void
    {
        $this->reset(['selectedAccountNo', 'migrationYear', 'migrationMonth']);
    }

    public function migrate(): void
    {
        $this->authorize(self::ABILITY);

        $this->validate([
            'selectedAccountNo' => 'required',
            'migrationYear' => 'required|integer',
            'migrationMonth' => 'required|integer|between:1,12',
        ], [
            'selectedAccountNo.required' => 'Search and select an account first.',
            'migrationYear.required' => 'Select the migration year.',
            'migrationMonth.required' => 'Select the migration month.',
        ]);

        $account = Subscriber::where('account_no', $this->selectedAccountNo)
            ->where('save_flag', 'F')
            ->where('isactive', true)
            ->first();

        if ($account === null) {
            $this->dispatch('notify', type: 'error', message: 'Account not found.');
            return;
        }
        if ($account->pension_type === 'U') {
            $this->dispatch('notify', type: 'error', message: 'This account is already migrated to UPS.');
            return;
        }
        if ($account->pension_type !== 'N') {
            $this->dispatch('notify', type: 'error', message: 'Only NPS accounts can be migrated.');
            return;
        }

        // Both writes commit together. The WHERE pension_type='N' guard stops a double migrate.
        $migrated = DB::transaction(function () {
            $updated = Subscriber::where('account_no', $this->selectedAccountNo)
                ->where('pension_type', 'N')
                ->update(['pension_type' => 'U']);

            if ($updated === 0) {
                return false;
            }

            DB::table('ups_migration')->insert([
                'user_id' => auth()->id(),
                'account_no' => $this->selectedAccountNo,
                'migration_year' => (int) $this->migrationYear,
                'migration_month' => (int) $this->migrationMonth,
                'entry_date' => now(),
            ]);

            return true;
        });

        if (! $migrated) {
            $this->dispatch('notify', type: 'error', message: 'Could not migrate — the account may already be UPS.');
            return;
        }

        $this->dispatch('notify', type: 'success', message: 'Account ' . $this->selectedAccountNo . ' migrated to UPS.');
        $this->closeForm();
    }

    public function render()
    {
        $results = collect();
        $account = null;

        if ($this->selectedAccountNo !== '') {
            $account = Subscriber::where('account_no', $this->selectedAccountNo)->first();
        } elseif (trim($this->search) !== '') {
            $term = '%' . strtolower($this->search) . '%';
            $results = Subscriber::query()
                ->where('save_flag', 'F')
                ->where('isactive', true)
                ->where(function ($w) use ($term) {
                    $w->whereRaw('LOWER(account_no) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(name) LIKE ?', [$term]);
                })
                ->orderBy('account_no')
                ->limit(15)
                ->get(['id', 'account_no', 'name', 'pension_type']);
        }

        // Years 2024 → current; months capped at the current month for the current year.
        $currentYear = (int) now()->year;
        $maxMonth = ((int) $this->migrationYear === $currentYear) ? (int) now()->month : 12;

        return view('livewire.accounts.migrate-to-ups', [
            'results' => $results,
            'account' => $account,
            'years' => range($currentYear, 2024),
            'maxMonth' => $maxMonth,
        ]);
    }
}
