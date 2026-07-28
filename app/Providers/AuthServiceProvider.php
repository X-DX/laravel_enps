<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // A single authorization hook drives the whole RBAC:
        //  - guests are left for the route middleware to reject,
        //  - Admins (role_flag 'A') bypass every check,
        //  - otherwise, allow when the user holds the permission whose key
        //    matches the ability; return null so non-RBAC abilities (e.g. model
        //    Policies) still get a chance to decide.
        //
        // hasPermissionTo() lazy-loads + memoises the user's permissions, so at
        // most one query per request — no permissions are enumerated at boot.
        Gate::before(function (?User $user, string $ability) {
            if (! $user) {
                return null;
            }

            if ($user->isAdmin()) {
                return true;
            }

            return $user->hasPermissionTo($ability) ? true : null;
        });
    }
}
