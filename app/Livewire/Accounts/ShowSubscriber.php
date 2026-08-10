<?php

namespace App\Livewire\Accounts;

use App\Models\Department;
use App\Models\Subscriber;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Shows the full details of ONE subscriber. The subscriber's id comes from the URL
 * (e.g. /accounts/5) and Laravel loads the record for us (route-model binding).
 */

#[Layout('components.layouts.app')]
class ShowSubscriber extends Component
{
    public int $subscriberId;

    public function mount(Subscriber $subscriber): void
    {
        $this->authorize('entrysection.view_all_accounts');

        // Laravel already fetched the subscriber from the URL id. We keep just its id
        // number, and re-load the full record in render() below.
        $this->subscriberId = $subscriber->id;
    }

    public function render()
    {
        $subscriber = Subscriber::with(['ddo.location', 'designationMaster', 'pran'])
            ->findOrFail($this->subscriberId);

        return view('livewire.accounts.show-subscriber', [
            'subscriber' => $subscriber,
            'departments' => Department::find(trim($subscriber->nameofdept)),
        ]);
    }
}
