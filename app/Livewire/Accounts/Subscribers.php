<?php

namespace App\Livewire\Accounts;

use App\Models\Department;
use App\Models\Subscriber;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use App\Exports\SubscribersExport;
use Maatwebsite\Excel\Facades\Excel;


/**
 * View All Accounts — a read-only, searchable register of every subscriber
 * (allotment_accnt_no). One screen replaces the legacy "View All / Pending / Finalized"
 * trio via a status filter.
 */
#[Layout('components.layouts.app')]
class Subscribers extends Component
{
    use WithPagination;
    private const ABILITY = 'entrysection.view_all_accounts';
    public int $perPage = 25;

    /** Free-text search over name + account number. */
    public string $search = '';

    /** '' = all · 'T' = pending (draft) · 'F' = finalized. */
    public string $status = '';

    public function mount(): void
    {
        $this->authorize(self::ABILITY);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function export()
    {
        $this->authorize(self::ABILITY);

        return Excel::download(new SubscribersExport($this->search, $this->status), 'subscribers-' . now()->format('Y-m-d') . '.xlsx');
    }


    // public function render()
    // {
    //     $subscribers = Subscriber::query()
    //         ->with(['ddo', 'designationMaster', 'pran']) // eager load to avoid N+1 queries on the DDO and designation columns
    //         ->when($this->search !== '', function ($query) {
    //             $term = '%' . strtolower($this->search) . '%';
    //             $query->where(function ($q) use ($term) {
    //                 $q->whereRaw('LOWER(name) LIKE ?', [$term])
    //                     ->orWhereRaw('LOWER(account_no) LIKE ?', [$term]);
    //             });
    //         })
    //         ->when($this->status !== '', fn($query) => $query->where('save_flag', $this->status))
    //         ->orderBy('id')
    //         ->paginate($this->perPage);

    //     return view('livewire.accounts.subscribers', [
    //         'subscribers' => $subscribers,
    //         // Tiny lookup map [dept_code => dept_name] — sidesteps the char-padding issue.
    //         'departments' => Department::pluck('dept_name', 'dept_code'),
    //     ]);
    // }
    public function render()
    {
        $subscribers = Subscriber::query()
            ->with(['ddo', 'designationMaster', 'pran'])
            ->filter($this->search, $this->status)
            ->orderBy('id')
            ->paginate($this->perPage);

        return view('livewire.accounts.subscribers', [
            'subscribers' => $subscribers,
            // Small [dept_code => dept_name] lookup for the Department column.
            'departments' => Department::pluck('dept_name', 'dept_code'),
        ]);
    }
}
