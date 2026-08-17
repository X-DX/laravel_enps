<?php

namespace App\Livewire\Accounts;

use App\Exports\PendingPransExport;
use App\Models\Department;
use App\Models\PranNo;
use App\Models\Subscriber;
use App\Models\Treasury;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;


/**
 * Assign PRAN Against Accounts (legacy menu 157). Search a finalized account, then add/update
 * its PRAN as a DRAFT (save_flag='T'). Finalizing the drafts is the pending list (slice 5.1b).
 */
#[Layout('components.layouts.app')]
class AssignPran extends Component
{
    use WithPagination;
    private const ABILITY = 'entrysection.assign_pran_against_accounts';

    /** The nodal office's fixed NSDL registration number (a constant in the legacy). */
    private const DDO_REG = 'SGV183455B';

    public string $search = '';
    public string $selectedAccountNo = '';

    public string $pranNo = '';
    public string $confirmPranNo = '';
    public string $pranAllotmentDate = '';   // column pran_allotment_date; shown as "PRAN Appointment Date"
    public bool $niraAccount = false;


    /** account_no's of the pending PRANs ticked for finalize/delete. */
    public array $selected = [];


    public function mount(): void
    {
        $this->authorize(self::ABILITY);
    }

    /** Pick an account from the search results → load its details + any existing PRAN. */
    public function selectAccount(string $accountNo): void
    {
        $account = Subscriber::where('account_no', $accountNo)
            ->where('save_flag', 'F')
            ->where('isactive', true)
            ->first();

        if ($account === null) {
            $this->dispatch('notify', type: 'error', message: 'Only a finalized, active account can get a PRAN.');
            return;
        }

        $this->selectedAccountNo = $accountNo;
        $this->search = '';
        $this->resetErrorBag();

        $pran = PranNo::where('account_no', $accountNo)->first();
        if ($pran) {
            $this->pranNo = $pran->pran_no ? number_format($pran->pran_no, 0, '.', '') : '';
            $this->confirmPranNo = $this->pranNo;
            $this->pranAllotmentDate = $pran->pran_allotment_date?->format('Y-m-d') ?? '';
            $this->niraAccount = (bool) $pran->nira_account;
        } else {
            $this->reset(['pranNo', 'confirmPranNo', 'pranAllotmentDate', 'niraAccount']);
        }
    }

    public function closeForm(): void
    {
        $this->reset(['selectedAccountNo', 'pranNo', 'confirmPranNo', 'pranAllotmentDate', 'niraAccount']);
        $this->resetErrorBag();
    }

    protected function rules(): array
    {
        return [
            // 12 digits, first digit 1–9; unique across pran_no except this account's own row.
            'pranNo' => ['required', 'regex:/^[1-9][0-9]{11}$/', Rule::unique('pran_no', 'pran_no')->ignore($this->selectedAccountNo, 'account_no')],
            'confirmPranNo' => ['required', 'same:pranNo'],
            'pranAllotmentDate' => ['required', 'date'],
        ];
    }

    protected function messages(): array
    {
        return [
            'pranNo.regex' => 'PRAN must be exactly 12 digits and cannot start with 0.',
            'pranNo.unique' => 'This PRAN is already assigned to another account.',
            'confirmPranNo.same' => 'PRAN and Confirm PRAN do not match.',
            'pranAllotmentDate.required' => 'Enter the PRAN appointment date.',
        ];
    }

    public function save(): void
    {
        $this->authorize(self::ABILITY);

        $account = Subscriber::where('account_no', $this->selectedAccountNo)
            ->where('save_flag', 'F')->where('isactive', true)->first();

        if ($account === null) {
            $this->dispatch('notify', type: 'error', message: 'Select a finalized account first.');
            return;
        }

        $this->validate();

        $data = [
            'pran_no' => $this->pranNo,
            'nira_account' => $this->niraAccount ? 1 : 0,
            'pran_allotment_date' => $this->pranAllotmentDate,
        ];

        $existing = PranNo::where('account_no', $this->selectedAccountNo)->first();

        if ($existing) {
            $existing->update($data);
            $message = 'PRAN updated.';
        } else {
            PranNo::create(array_merge($data, [
                'account_no' => $this->selectedAccountNo,
                'ddo_reg' => self::DDO_REG,
                'save_flag' => 'T',
                'entry_date' => now()->toDateString(),
                'user_id' => auth()->id(),
                'is_active' => 1,
            ]));
            $message = 'PRAN saved as a draft.';
        }

        $this->dispatch('notify', type: 'success', message: $message);
        $this->closeForm();
    }

    /* ---- the pending list (draft PRANs) ---- */

    public function finalizeSelected(): void
    {
        $this->authorize(self::ABILITY);

        if (empty($this->selected)) {
            $this->dispatch('notify', type: 'error', message: 'Select at least one pending PRAN.');
            return;
        }

        $count = PranNo::whereIn('account_no', $this->selected)
            ->where('save_flag', 'T')
            ->update(['save_flag' => 'F', 'finalize_date' => now()->toDateString()]);

        $this->selected = [];
        $this->dispatch('notify', type: 'success', message: 'Finalized ' . $count . ' PRAN(s).');
    }

    public function deleteSelected(): void
    {
        $this->authorize(self::ABILITY);

        if (empty($this->selected)) {
            $this->dispatch('notify', type: 'error', message: 'Select at least one pending PRAN.');
            return;
        }

        // Only drafts can be deleted — a finalized PRAN is never removed here.
        $count = PranNo::whereIn('account_no', $this->selected)
            ->where('save_flag', 'T')
            ->delete();

        $this->selected = [];
        $this->dispatch('notify', type: 'success', message: 'Deleted ' . $count . ' draft PRAN(s).');
    }

    public function toggleSelectAll(): void
    {
        $pageKeys = PranNo::query()
            ->where('save_flag', 'T')
            ->orderByDesc('entry_date')
            ->paginate(15)
            ->pluck('account_no')->map(fn($a) => (string) $a)->all();

        $allSelected = count($pageKeys) > 0 && count(array_diff($pageKeys, $this->selected)) === 0;

        $this->selected = $allSelected
            ? array_values(array_diff($this->selected, $pageKeys))
            : array_values(array_unique([...$this->selected, ...$pageKeys]));
    }

    public function exportPending()
    {
        $this->authorize(self::ABILITY);

        return Excel::download(new PendingPransExport(), 'pending-prans-' . now()->format('Y-m-d') . '.xlsx');
    }

    public function pdfPending()
    {
        $this->authorize(self::ABILITY);

        $prans = PranNo::query()
            ->with(['subscriber', 'subscriber.designationMaster', 'subscriber.ddo'])
            ->where('save_flag', 'T')
            ->orderByDesc('entry_date')
            ->get();

        $pdf = Pdf::loadView('pdf.pending-prans', ['prans' => $prans, 'generatedAt' => now()])->setPaper('a4', 'landscape');

        return response()->streamDownload(fn() => print($pdf->output()), 'pending-prans-' . now()->format('Y-m-d') . '.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }


    public function render()
    {
        $results = collect();
        $account = null;
        $mode = 'add';
        $treasuryName = '';

        if ($this->selectedAccountNo !== '') {
            $account = Subscriber::with(['designationMaster', 'ddo'])
                ->where('account_no', $this->selectedAccountNo)->first();
            $mode = PranNo::where('account_no', $this->selectedAccountNo)->exists() ? 'update' : 'add';

            if ($account?->ddo?->treasury_code) {
                $treasuryName = Treasury::where('treasury_code', $account->ddo->treasury_code)->value('treasury_name') ?? '';
            }
        } elseif (trim($this->search) !== '') {
            $term = '%' . strtolower($this->search) . '%';
            $results = Subscriber::query()
                ->where('save_flag', 'F')->where('isactive', true)
                ->where(function ($w) use ($term) {
                    $w->whereRaw('LOWER(account_no) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(name) LIKE ?', [$term]);
                })
                ->orderBy('account_no')->limit(15)->get(['id', 'account_no', 'name']);
        }

        $pending = PranNo::query()
            ->with(['subscriber', 'subscriber.designationMaster', 'subscriber.ddo'])
            ->where('save_flag', 'T')
            ->orderByDesc('entry_date')
            ->paginate(15);

        return view('livewire.accounts.assign-pran', [
            'results' => $results,
            'account' => $account,
            'mode' => $mode,
            'treasuryName' => $treasuryName,
            'pending' => $pending,
            'pagePendingKeys' => $pending->pluck('account_no')->map(fn($a) => (string) $a)->all(),
            'departments' => Department::pluck('dept_name', 'dept_code'),
        ]);
    }
}
