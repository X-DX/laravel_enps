<?php

namespace App\Livewire\Auth;

use App\Events\UserAuthenticated;
use App\Support\Captcha\CaptchaService;
use Illuminate\Support\Facades\Auth; // provides methods for authentication : Log users in, Log users out, Check if a user is logged in, Get the currently authenticated user: eg: Auth::attempt($credentials); // Login, Auth::check(); // true if logged in
use Illuminate\Support\Facades\RateLimiter; // server-side attempt throttling (backed by the cache), not the session.
use Illuminate\Support\Facades\Session; // A session stores data between requests.
use Illuminate\Support\Str; // string helpers (used to build a normalised throttle key).
use Illuminate\Validation\ValidationException; // Imports the exception class used when validation fails. Usually used when you want to manually throw a validation error.
use Livewire\Attributes\Layout; // This imports the Layout Attribute. Instead of specifying a layout elsewhere, you can declare it directly above your component method.
use Livewire\Attributes\Validate; // Validation attribute, Instead of writing validation rules inside a rules() method, you attach them directly to component properties.
use Livewire\Component; // Every Livewire component extends this base class.

#[Layout('components.layouts.guest')]
class Login extends Component
{
    /** Maximum failed attempts (per user id + IP) before a temporary lockout. */
    private const MAX_ATTEMPTS = 5;

    /**
     * Operators authenticate with their `user_id` (matching the legacy system,
     * which queried `user_account.user_id`), not the display username.
     */
    #[Validate('required|string')]
    public string $userId = '';

    #[Validate('required|string')]
    public string $password = '';

    #[Validate('required|string')]
    public string $captcha = '';

    public function login()
    {
        $this->validate();

        $this->ensureIsNotRateLimited();

        // Anti-bot: the CAPTCHA must match the code stored in the session.
        $captcha = app(CaptchaService::class);
        if (! $captcha->verify($this->captcha)) {
            /* 
            RateLimiter::hit($key, $decaySeconds) is an alternative, more powerful version of RateLimiter::increment(). It does two things at the exact same time:
            1. It records the request (increments the count by 1 in the cache).
            2. It returns the total number of hits recorded so far.
            */
            RateLimiter::hit($this->throttleKey());
            $this->reset('captcha');

            throw ValidationException::withMessages([
                'captcha' => __('The captcha is incorrect or has expired.'),
            ]);
        }

        // Verifies the password through our custom hasher (legacy SHA-256 or
        // bcrypt) and, on success, auto-upgrades a legacy hash to bcrypt.
        if (! Auth::attempt(['user_id' => $this->userId, 'password' => $this->password])) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'userId' => __('These credentials do not match our records.'),
            ]);
        }

        // Gate: the account must be enabled (legacy user_status flag).
        if (! Auth::user()->user_status) {
            Auth::logout();
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'userId' => __('Your account is disabled. Please contact the administrator.'),
            ]);
        }

        // Gate: per-user IP lock (legacy sys_ip). If an IP is registered for the
        // account, the request must originate from it.
        $user = Auth::user();
        if (filled($user->sys_ip) && $user->sys_ip !== request()->ip()) {
            Auth::logout();
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'userId' => __('This account may only sign in from an authorised network.'),
            ]);
        }

        // Success: clear the throttle, invalidate the one-time CAPTCHA, and
        // rotate the session id to prevent fixation.
        RateLimiter::clear($this->throttleKey());
        $captcha->forget();
        Session::regenerate();

        // Record the login in the audit trail (event → RecordLoginHistory listener).
        UserAuthenticated::dispatch($user, request()->ip(), (string) request()->userAgent());

        return $this->redirect(route('dashboard'), true);
    }

    /**
     * A throttle key unique to this user id + client IP.
     */
    private function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->userId) . '|' . request()->ip());
    }

    /**
     * Stop further attempts once the limit is reached, with a countdown message.
     */
    private function ensureIsNotRateLimited(): void
    {
        // RateLimiter::tooManyAttempts(): Checks if the number of attempts exceeds the maximum allowed
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        // RateLimiter::availableIn($key) is a helpful method that calculates exactly how many seconds a user has to wait before they are allowed to try again.
        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'userId' => __('Too many login attempts. Please try again in :seconds seconds.', ['seconds' => $seconds]),
        ]);
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
