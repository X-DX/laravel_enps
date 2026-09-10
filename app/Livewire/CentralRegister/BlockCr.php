<?php

namespace App\Livewire\CentralRegister;

use App\Models\CentralReg;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Block / Unblock CR entries (legacy menus 205 & 206). ONE component, two modes:
 *   'block'   → Block CR Entry   — finalized entries not yet blocked; each can be blocked with a reason
 *   'blocked' → Blocked CR List  — blocked entries; each can be unblocked or have its reason edited
 *
 * Blocking freezes ONE Central Register row, addressed by its CR No (central_reg.sl_no, now a PK).
 * It never deletes — a statutory register keeps every entry; blocking only flags it. The screens are
 * central_reg-centric (one row per CR No) because a double-booked draft has more than one CR No and
 * you must be able to block the exact one. Per-user (admins see all), matching the legacy filter.
 */
#[Layout('components.layouts.app')]
class BlockCr extends Component
{
    use WithPagination;

    public string $mode = 'block';
    public int $perPage = 25;
    public string $search = '';

    /** Reason modal state. */
    public bool $showModal = false;
    public ?int $modalCrNo = null;
    public string $modalAction = 'block';   // 'block' | 'edit'
    public string $reason = '';

    private const ABILITIES = [
        'block' => 'entrysection.block_cr_nos',
        'blocked' => 'entrysection.blocked_cr_lists',
    ];

    private const TITLES = [
        'block' => 'Block CR Entry',
        'blocked' => 'Blocked CR List',
    ];

    private const EAGER = ['firstReceipt.ddo.treasury', 'firstReceipt.ddo.location'];

    public function mount(string $mode = 'block'): void
    {
        $this->mode = $mode;
        $this->authorize(self::ABILITIES[$mode]);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    /**
     * Central Register rows for this screen: not-yet-blocked (block mode) or blocked (blocked mode),
     * narrowed to the current user unless they are an admin. Search matches CR No / Receipt No /
     * First Receipt No (numeric) or draft/order no (text, via the parent receipt).
     */
    protected function baseQuery(): Builder
    {
        return CentralReg::query()
            ->where('blocked_cr', $this->mode === 'blocked')
            ->when(! auth()->user()?->isAdmin(), fn (Builder $q) => $q->where('user_id', auth()->id()))
            ->when(trim($this->search) !== '', function (Builder $q) {
                $term = trim($this->search);

                if (ctype_digit($term)) {
                    $n = (int) $term;
                    $q->where(fn (Builder $q) => $q
                        ->where('sl_no', $n)
                        ->orWhere('receipt_no', $n)
                        ->orWhere('first_receipt_sl_no', $n));
                } else {
                    $like = '%' . strtolower($term) . '%';
                    $q->whereHas('firstReceipt', fn (Builder $r) => $r
                        ->whereRaw('LOWER(draft_no) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(order_no) LIKE ?', [$like]));
                }
            });
    }

    /** Fetch a CR row the current user is allowed to act on (owner, or admin). */
    private function ownedCr(?int $crNo): ?CentralReg
    {
        if ($crNo === null) {
            return null;
        }

        return CentralReg::query()
            ->where('sl_no', $crNo)
            ->when(! auth()->user()?->isAdmin(), fn (Builder $q) => $q->where('user_id', auth()->id()))
            ->first();
    }

    public function openBlock(int $crNo): void
    {
        $this->authorize(self::ABILITIES[$this->mode]);
        $this->resetValidation();
        $this->modalCrNo = $crNo;
        $this->modalAction = 'block';
        $this->reason = '';
        $this->showModal = true;
    }

    public function openEdit(int $crNo): void
    {
        $this->authorize(self::ABILITIES[$this->mode]);
        $this->resetValidation();
        $this->modalCrNo = $crNo;
        $this->modalAction = 'edit';
        $this->reason = (string) $this->ownedCr($crNo)?->blocked_reason;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->reset('showModal', 'modalCrNo', 'reason', 'modalAction');
    }

    /** Save the modal: block a new entry, or edit an already-blocked entry's reason. */
    public function save(): void
    {
        $this->authorize(self::ABILITIES[$this->mode]);
        $this->validate(['reason' => ['required', 'string', 'max:255']], [
            'reason.required' => 'A reason is required.',
        ]);

        $blocking = $this->modalAction === 'block';

        // Guarded + owner-scoped update: only flips a row that is in the expected state and ours.
        $affected = CentralReg::query()
            ->where('sl_no', $this->modalCrNo)
            ->where('blocked_cr', ! $blocking)   // block: currently unblocked · edit: currently blocked
            ->when(! auth()->user()?->isAdmin(), fn (Builder $q) => $q->where('user_id', auth()->id()))
            ->update($blocking
                ? ['blocked_cr' => true, 'blocked_reason' => $this->reason, 'blocked_date' => now()->toDateString(), 'blocked_by_user' => auth()->id()]
                : ['blocked_reason' => $this->reason]);

        if ($affected === 0) {
            $this->dispatch('notify', type: 'error', message: 'That CR entry could not be updated — refresh and try again.');
            $this->closeModal();
            return;
        }

        $crNo = $this->modalCrNo;
        $this->closeModal();
        $this->dispatch('notify', type: 'success', message: $blocking ? "CR No {$crNo} blocked." : "Reason updated for CR No {$crNo}.");
    }

    public function unblock(int $crNo): void
    {
        $this->authorize(self::ABILITIES[$this->mode]);

        $affected = CentralReg::query()
            ->where('sl_no', $crNo)
            ->where('blocked_cr', true)
            ->when(! auth()->user()?->isAdmin(), fn (Builder $q) => $q->where('user_id', auth()->id()))
            ->update(['blocked_cr' => false, 'blocked_reason' => null, 'blocked_date' => null, 'blocked_by_user' => null]);

        $this->dispatch('notify',
            type: $affected ? 'success' : 'error',
            message: $affected ? "CR No {$crNo} unblocked." : "CR No {$crNo} could not be unblocked.");
    }

    public function render()
    {
        $entries = $this->baseQuery()
            ->with(self::EAGER)
            ->orderByDesc('sl_no')
            ->paginate($this->perPage);

        return view('livewire.central-register.block-cr', [
            'entries' => $entries,
            'title' => self::TITLES[$this->mode] ?? self::TITLES['block'],
        ]);
    }
}
