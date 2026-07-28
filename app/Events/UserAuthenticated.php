<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after an operator has fully passed every login gate (credentials,
 * account status, IP lock). Listeners can react to a genuine successful login
 * — e.g. writing the audit trail — without cluttering the login component.
 */
class UserAuthenticated
{
    use Dispatchable;

    public function __construct(
        public User $user,
        public ?string $ip,
        public ?string $userAgent,
    ) {
    }
}
