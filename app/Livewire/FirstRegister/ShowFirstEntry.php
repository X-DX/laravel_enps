<?php

namespace App\Livewire\FirstRegister;

use App\Models\FirstReceipt;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The detail page for one first-register receipt (by Receipt No / sl_no), reachable from any of
 * the three list screens.
 */
#[Layout('components.layouts.app')]
class ShowFirstEntry extends Component
{
    public FirstReceipt $firstReceipt;

    public function mount(FirstReceipt $firstReceipt): void
    {
        $this->authorize('entrysection.view_all_first_entries');
        $this->firstReceipt = $firstReceipt;
    }

    public function render()
    {
        return view('livewire.first-register.show-first-entry', [
            'entry' => $this->firstReceipt->load(['ddo', 'bank', 'purposeCode']),
        ]);
    }
}
