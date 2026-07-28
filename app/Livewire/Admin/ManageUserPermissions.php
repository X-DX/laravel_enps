<?php

namespace App\Livewire\Admin;

use App\Models\Permission;
use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ManageUserPermissions extends Component
{
    /** The permission required to manage other users' access. */
    private const ABILITY = 'adminsection.add_update_user';

    public string $userId = '';

    /** Currently selected permission ids (as strings, to match checkbox values). */
    public array $selected = [];

    public bool $saved = false;

    public function mount(): void
    {
        $this->authorize(self::ABILITY);
    }

    /**
     * When the chosen user changes, load their current direct grants.
     */
    public function updatedUserId(): void
    {
        $this->saved = false;

        $this->selected = $this->userId
            ? User::find($this->userId)?->directPermissions()
                ->pluck('permissions.id')->map(fn ($id) => (string) $id)->all() ?? []
            : [];
    }

    public function save(): void
    {
        $this->authorize(self::ABILITY);

        if ($this->userId === '') {
            return;
        }

        User::findOrFail($this->userId)
            ->directPermissions()
            ->sync($this->selected);

        $this->saved = true;
    }

    public function render()
    {
        return view('livewire.admin.manage-user-permissions', [
            'users' => User::query()->orderBy('username')->get(['user_id', 'username', 'role_flag']),
            'groups' => Permission::query()->orderBy('legacy_menu_id')->get()->groupBy('group'),
            'selectedUser' => $this->userId !== '' ? User::find($this->userId) : null,
        ]);
    }
}
