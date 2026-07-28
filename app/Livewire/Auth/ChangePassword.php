<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ChangePassword extends Component
{
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function update()
    {
        $this->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ]);

        $user = Auth::user();

        // Re-verify the current password (works for legacy SHA-256 or bcrypt).
        if (! Hash::check($this->current_password, $user->getAuthPassword())) {
            throw ValidationException::withMessages([
                'current_password' => __('Your current password is incorrect.'),
            ]);
        }

        // Store the new password as bcrypt and clear the "must change" condition.
        $user->forceFill([
            'password' => Hash::make($this->password),
            'last_pwd_change' => now()->toDateString(),
            'first_login' => 1,
        ])->save();

        Session::regenerate();
        session()->flash('status', __('Your password has been updated.'));

        return $this->redirect(route('dashboard'), true);
    }

    public function render()
    {
        return view('livewire.auth.change-password');
    }
}
