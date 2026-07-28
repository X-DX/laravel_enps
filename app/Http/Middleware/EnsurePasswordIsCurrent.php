<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces an authenticated user whose password is expired, unset, or awaiting a
 * first-login change to the change-password screen before they can use the app.
 *
 * Applied only to protected routes; the change-password and logout routes are
 * intentionally left outside this middleware to avoid a redirect loop.
 */
class EnsurePasswordIsCurrent
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->mustChangePassword()) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
