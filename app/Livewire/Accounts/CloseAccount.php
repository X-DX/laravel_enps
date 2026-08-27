<?php

namespace App\Livewire\Accounts;

use App\Exports\ClosedAccountsExport;
use App\Models\AccountClosure;
use App\Models\ClosureReason;
use App\Models\Department;
use App\Models\Subscriber;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Close Account (legacy menu 234). Cascade form: department → account → (name + PRAN shown) →
 * reason + closing details → close. Closing writes ONE account_closure row and flips
 * allotment_accnt_no.isactive to false, in a single transaction. The closed register below
 * reads account_closure (real closures), searchable + Excel + PDF.
 */
#[Layout('components.layouts.app')]
class CloseAccount extends Component
{
    use WithPagination;

    private const ABILITY = 'entrysection.close_account';

    public string $departmentCode = '';
    public string $accountNo = '';
    public string $name = '';          // shown read-only, from the chosen account
    public string $pranNo = '';        // shown read-only, from the chosen account
    public string $closeReason = '';
    public string $closingDate = '';
    public string $lastContributionMonth = '';
    public string $lastContributionYear = '';

    /** Search box for the Closed Accounts register below. */
    public string $closedSearch = '';

    public function mount(): void
    {
        $this->authorize(self::ABILITY);
    }

    /** Department changed → clear the account and its derived fields. */
    public function updatedDepartmentCode(): void
    {
        $this->reset(['accountNo', 'name', 'pranNo']);
    }

    /** Account chosen → fill Name + PRAN (only an open, finalized account qualifies). */
    public function updatedAccountNo(): void
    {
        $this->name = '';
        $this->pranNo = '';

        if ($this->accountNo === '') {
            return;
        }

        $account = Subscriber::acrossOperators()
            ->openFinalized()
            ->with('pran')
            ->where('account_no', $this->accountNo)
            ->first();

        if ($account === null) {
            $this->dispatch('notify', type: 'error', message: 'That account is not open for closure.');
            $this->accountNo = '';
            return;
        }

        $this->name = $account->name;
        $this->pranNo = $account->pran ? number_format($account->pran->pran_no, 0, '.', '') : '';
    }

    public function updatingClosedSearch(): void
    {
        $this->resetPage();
    }

    public function close(): void
    {
        $this->authorize(self::ABILITY);

        $this->validate([
            'departmentCode' => 'required',
            'accountNo' => 'required',
            'closeReason' => 'required|integer',
            'closingDate' => 'required|date',
            'lastContributionMonth' => 'required|integer|between:1,12',
            'lastContributionYear' => 'required|integer|min:2000|max:2100',
        ], [
            'departmentCode.required' => 'Select a department.',
            'accountNo.required' => 'Select an account number.',
            'closeReason.required' => 'Select a closure reason.',
            'closingDate.required' => 'Enter the closing date.',
            'lastContributionMonth.required' => 'Select the last contribution month.',
            'lastContributionYear.required' => 'Enter the last contribution year.',
        ]);

        // Both steps commit together or not at all.
        $closed = DB::transaction(function () {
            // Guard: only a still-open account is closed — never twice.
            $updated = Subscriber::acrossOperators()
                ->openFinalized()
                ->where('account_no', $this->accountNo)
                ->update(['isactive' => false]);

            if ($updated === 0) {
                return false;
            }

            AccountClosure::create([
                'account_no' => $this->accountNo,
                'closure_reason_id' => (int) $this->closeReason,
                'closing_date' => $this->closingDate,
                'last_contribution_month' => (int) $this->lastContributionMonth,
                'last_contribution_year' => (int) $this->lastContributionYear,
                'closed_by' => auth()->id(),
            ]);

            return true;
        });

        if (! $closed) {
            $this->dispatch('notify', type: 'error', message: 'Account is already closed.');
            return;
        }

        $this->dispatch('notify', type: 'success', message: 'Account ' . $this->accountNo . ' closed.');
        $this->reset(['departmentCode', 'accountNo', 'name', 'pranNo', 'closeReason', 'closingDate', 'lastContributionMonth', 'lastContributionYear']);
    }

    public function exportClosed()
    {
        $this->authorize(self::ABILITY);

        return Excel::download(new ClosedAccountsExport($this->closedSearch), 'closed-accounts-' . now()->format('Y-m-d') . '.xlsx');
    }

    public function pdfClosed()
    {
        $this->authorize(self::ABILITY);

        $closures = AccountClosure::query()
            ->with(['reason', 'subscriber'])
            ->search($this->closedSearch)
            ->orderByDesc('closing_date')
            ->get();

        $pdf = Pdf::loadView('pdf.closed-accounts', [
            'closures' => $closures,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        return response()->streamDownload(fn() => print($pdf->output()), 'closed-accounts-' . now()->format('Y-m-d') . '.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function render()
    {
        // Active, finalized accounts in the chosen department → the Account No dropdown.
        $accountOptions = $this->departmentCode === ''
            ? []
            : Subscriber::acrossOperators()
            ->openFinalized()
            ->whereRaw('trim(nameofdept) = ?', [trim($this->departmentCode)])
            ->orderBy('account_no')
            ->pluck('account_no')
            ->map(fn($a) => ['value' => (string) $a, 'label' => trim((string) $a)])
            ->all();

        $closedAccounts = AccountClosure::query()
            ->with(['reason', 'subscriber'])
            ->search($this->closedSearch)
            ->orderByDesc('closing_date')
            ->paginate(10);

        return view('livewire.accounts.close-account', [
            'departmentOptions' => Department::orderBy('dept_name')
                ->get()
                ->map(fn($d) => ['value' => trim($d->dept_code), 'label' => trim($d->dept_name)])
                ->all(),
            'accountOptions' => $accountOptions,
            'reasons' => ClosureReason::orderBy('id')->get(),
            'closedAccounts' => $closedAccounts,
        ]);
    }
}
