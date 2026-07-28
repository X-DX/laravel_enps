# M1 — Auth & Access (Explained for Beginners)

> **What this milestone gives you, in one sentence:**
> The **59 real users** from the legacy system log in to the new Laravel app **with
> their existing passwords**, on a modern secure stack — and their old weak password
> hashes get silently upgraded the first time they sign in.

Nobody has to reset a password. Nobody is locked out. But under the hood everything
is now modern: bcrypt hashing, server-side rate limiting, a CAPTCHA, IP locking,
password expiry, and an audit trail.

This document is written to **teach**, not just to record. If you're new to Laravel,
read section **A (concepts)** first, then **B (the flow)**, then the slices.

---

## A. Concepts you need first (mini-glossary)

Read these once; the rest of the doc assumes them.

| Term | Plain-English meaning | Where we use it |
|------|----------------------|-----------------|
| **Model (Eloquent)** | A PHP class that represents **one database table**. `User` ↔ `user_account`. You read/write rows as objects: `User::find('admin')`. Eloquent is Laravel's ORM (Object-Relational Mapper). | `app/Models/User.php` |
| **Hasher** | A component that turns a password into a scrambled **hash** and later checks a typed password against a stored hash. You never store the real password. | `LegacyPasswordHasher` |
| **Service Provider** | A "startup script." Laravel runs each provider's `boot()` method when the app starts, so you can **register** custom services there. | `AppServiceProvider` registers our hasher |
| **Service Container** | Laravel's "factory" that builds objects and injects their dependencies for you (Dependency Injection). `app(CaptchaService::class)` asks the container for a ready-to-use captcha service. | throughout |
| **Livewire component** | A PHP class + a Blade view that together form a **reactive UI**. Public properties are the state; public methods are actions triggered from the page. No hand-written JavaScript needed. | `Login`, `ChangePassword` |
| **Session** | Per-user storage that **persists between requests** (backed by a cookie holding a session id). This is how the app "remembers" you're logged in. | login, captcha code |
| **Middleware** | A filter that wraps a request **before** it reaches your page. Great for cross-cutting rules like "must be logged in" or "must have a current password." | `EnsurePasswordIsCurrent` |
| **Event & Listener** | An **event** is an announcement ("a user just logged in"); a **listener** reacts to it ("write a row to the audit log"). This *decouples* the announcement from the reaction. | `UserAuthenticated` → `RecordLoginHistory` |
| **Rate Limiter** | A server-side counter that blocks an action after N attempts in a time window. Used to stop password-guessing. | login throttle |
| **Validation** | Rules that reject bad input *before* your logic runs (`required`, `string`, …). | login form fields |
| **Gate / guard (auth)** | `Auth::attempt()` checks credentials and logs the user in; a "guard" is the auth mechanism for a route group (we use the `web` session guard). | login |

---

## B. The big picture — what happens when someone logs in

```text
User opens /login
        │
        ▼
Login page renders (Livewire component "Login")
  • shows User ID, Password, CAPTCHA fields
  • <img src="/captcha"> loads a random image; the code is saved in the SESSION
        │
        ▼
User submits  ──►  Login::login()  runs on the server
        │
        ▼
1. validate()                 → fields present? (required|string)
        │
        ▼
2. ensureIsNotRateLimited()   → too many recent failures for this user+IP? → STOP
        │
        ▼
3. captcha->verify()          → typed code == session code? if not → hit() + STOP
        │
        ▼
4. Auth::attempt(['user_id'=>…,'password'=>…])
        │         │
        │         └─► goes through EloquentUserProvider → our LegacyPasswordHasher::check()
        │                 • bcrypt hash?  → bcrypt check
        │                 • 64-hex hash?  → sha256(input) compared with hash_equals()
        │
        │         └─► on success, Laravel calls needsRehash():
        │                 • SHA-256 → true → make() a bcrypt hash → UPDATE user_account
        │                   (this is the silent upgrade — happens once per user)
        │
        │  wrong password → RateLimiter::hit() + STOP
        ▼
5. Gate: user_status enabled?         → no → logout + hit() + STOP
        │
        ▼
6. Gate: sys_ip matches request IP?   → no → logout + hit() + STOP
        │
        ▼
7. Success:
   • RateLimiter::clear()   (reset the counter)
   • captcha->forget()      (one-time use)
   • Session::regenerate()  (new session id → prevents "session fixation" attacks)
   • dispatch UserAuthenticated → RecordLoginHistory writes to login_log
        │
        ▼
Redirect to /dashboard
        │
        ▼
EnsurePasswordIsCurrent middleware checks mustChangePassword()
   • first login, or no change date, or older than 30 days → /password/change
   • otherwise → dashboard
```

The five slices below build this pipeline piece by piece.

---

## Slice 1a — The `User` model → `user_account`

**What:** Point Laravel's `User` model at the **legacy `user_account` table** instead
of a brand-new `users` table.

**Why:** We reuse the migrated production data as-is, so existing accounts work
immediately (this is the "compatibility-first / Phase A" rule from `architecture.md`).

**File:** `app/Models/User.php`

**The important lines, explained:**

```php
protected $table = 'user_account';   // this Model maps to the legacy table
protected $primaryKey = 'user_id';   // the PK column is user_id...
public $incrementing = false;        // ...and it is NOT an auto-increment number
protected $keyType = 'string';       // ...it's a varchar like "admin"
public $timestamps = false;          // legacy table has no created_at/updated_at
```

> **Why `incrementing = false` + `keyType = 'string'` matter:** by default Eloquent
> assumes the primary key is an auto-incrementing integer. If we left the defaults,
> Laravel would cast the key `"admin"` to the number `0` and break lookups. These two
> flags tell Eloquent "the key is a hand-assigned string."

```php
protected $hidden = ['password'];     // never leak the hash in JSON/array output
```

```php
protected function casts(): array {
    return [
        'user_status'     => 'boolean',
        'first_login'     => 'boolean',
        'last_pwd_change' => 'date',
        // NOTE: 'password' is deliberately NOT cast to 'hashed'
    ];
}
```

> **Why NO `'password' => 'hashed'` cast:** that built-in cast auto-bcrypts any value
> you assign to `password`. But our migrated passwords are **SHA-256**, and we want a
> *custom* hasher to handle both formats (slice 1b). So we opt out of the cast.

```php
public function getRememberTokenName() { return ''; }
```

> **Why:** "Remember me" needs a `remember_token` column, which `user_account` doesn't
> have. Returning an empty column name **disables** the feature cleanly instead of
> crashing.

`mustChangePassword()` (used later in 1e) returns `true` if the user never completed a
first login, has no recorded change date, or the password is older than 30 days.

**Verified:** `User::count()` = 59; `admin` resolves; password hidden from output.

---

## Slice 1b — The custom hasher (SHA-256 **and** bcrypt)

**What:** A hasher that can **verify both** the legacy unsalted SHA-256 hashes and
modern bcrypt hashes, but **always creates** bcrypt, and reports SHA-256 hashes as
"needs upgrading."

**Why:** The legacy system stored passwords as **unsalted SHA-256** — fast to brute
force and considered weak today. We want bcrypt, but we can't ask 59 users to reset.
So: accept the old format at login, and quietly re-hash to bcrypt on the way in.

**Files:**
- `app/Support/Hashing/LegacyPasswordHasher.php` — the hasher itself
- `config/hashing.php` — makes `legacy` the app-wide default driver
- `app/Providers/AppServiceProvider.php` — registers the `legacy` driver

**How each method works** (`LegacyPasswordHasher`):

```php
public function make($value): string
    → always returns a bcrypt hash (delegates to Laravel's BcryptHasher)
```
> Every *new* or *changed* password is stored as bcrypt. The app only ever *creates*
> modern hashes.

```php
public function check($value, $hashedValue): bool
    if hash looks like bcrypt   → bcrypt->check()
    if hash is 64 hex chars     → hash_equals( strtolower(stored), sha256(input) )
    else                        → false
```
> **Why `hash_equals`** instead of `==`: it's a **constant-time** comparison, which
> prevents "timing attacks" (an attacker measuring how long a comparison takes to
> guess the hash character by character).

```php
public function needsRehash($hashedValue): bool
    if bcrypt → only if the cost/work-factor changed
    else      → true   (any legacy/SHA-256 hash should be upgraded)
```

**The magic wiring — how this runs automatically:**

`config/hashing.php` sets the default driver to `legacy`. Then `AppServiceProvider`
registers what `legacy` means:

```php
Hash::extend('legacy', function ($app) {
    return new LegacyPasswordHasher(new BcryptHasher([...]));
});
```

Because `legacy` is now the **app-wide default**, both the `Hash` facade *and*
Laravel's login machinery (`EloquentUserProvider`) use it **without any special code
in the controller**. Laravel has a built-in "rehash on login" step: after a successful
`Auth::attempt`, it calls `needsRehash()`, and if `true`, calls `make()` and
**UPDATEs the user's password to bcrypt**. That's the silent, one-time upgrade.

**Verified:** `check(sha256)=true`, `needsRehash(sha256)=true`, `make()=bcrypt`,
`check(bcrypt)=true`, `needsRehash(bcrypt)=false`. 5 unit tests pass.

> **Future cleanup:** once telemetry shows every user has logged in (and upgraded),
> delete the SHA-256 branch — a post-cutover task.

---

## Slice 1c — The login screen + login flow (Livewire)

**What:** A beautiful login page (Livewire 4 + Tailwind) that authenticates the real
`user_account` users, plus logout and a placeholder dashboard.

**Why:** The first real UI, and the moment the 59 migrated users actually sign in.

**Files:**
- `app/Livewire/Auth/Login.php` — the component (state + the `login()` action)
- `resources/views/livewire/auth/login.blade.php` — the card UI
- `resources/views/components/layouts/guest.blade.php` — the auth page layout
- `routes/web.php` — wires URLs to components

**How a Livewire login works (the mental model):**

```php
#[Layout('components.layouts.guest')]     // which page shell to render inside
class Login extends Component
{
    #[Validate('required|string')] public string $userId = '';
    #[Validate('required|string')] public string $password = '';
    #[Validate('required|string')] public string $captcha = '';

    public function login() { ... }        // runs on the SERVER when the form submits
}
```

- Each `public` property is bound to an input in the Blade view; Livewire keeps them
  in sync automatically.
- `#[Validate(...)]` is an **attribute** — a modern PHP way to attach the validation
  rule right on the property (instead of a separate `rules()` array).
- `login()` runs server-side; it either throws a `ValidationException` (which shows an
  inline error) or redirects on success.

**The single most important decision here:**

```php
Auth::attempt(['user_id' => $this->userId, 'password' => $this->password])
```

> **We authenticate by `user_id`, NOT `username`.** The legacy system queried
> `user_account.user_id`. Operators type their **user ID**, not the display name.
> Getting this wrong was an easy trap — we caught it and wrote a test to guard it.
> Because `Auth::attempt` runs through our custom hasher, a legacy user logs in **and**
> gets upgraded to bcrypt in the same request.

Right after the attempt, we add the **enabled-account gate** (`user_status`): a
disabled account is immediately logged out with an error.

**Gotchas we hit (worth remembering):**
- In Livewire component tests, `request()->session()` is **not bound** — you get
  "Session store not set on request." Use the **`Session` facade**
  (`Session::regenerate()`), which is also the correct thing in the real flow.
- Pages that render `@vite(...)` need built assets → run `npm run build` before tests.
- The stock `.env` used database sessions/cache, but our schema has no such tables →
  500 on first load. We switched both to `file` (see M0).

---

## Slice 1d — Rate limiting + CAPTCHA (stop password guessing / bots)

**What:** Replace the legacy session-based throttle with Laravel's **server-side**
`RateLimiter`, and add a self-contained image **CAPTCHA**.

**Why:** The legacy throttle lived in the session cookie — drop the cookie and the
counter resets (useless against a determined attacker). A server-side limiter can't be
bypassed that way. The CAPTCHA blocks automated bots.

**Files:**
- `app/Support/Captcha/CaptchaService.php` — draws a PNG, stores the code in the session, verifies it
- `routes/web.php` — `GET /captcha` serves the image
- `app/Livewire/Auth/Login.php` — throttle + captcha checks
- `resources/views/livewire/auth/login.blade.php` — captcha image + refresh button

**How the rate limiter works (in `Login.php`):**

```php
private function throttleKey(): string
    → lower(userId) + '|' + client IP     // one counter per user-per-IP

ensureIsNotRateLimited()
    → if RateLimiter::tooManyAttempts(key, 5) → throw "try again in N seconds"

// on each failure:
RateLimiter::hit($key)     // increment the counter (5-min decay)
// on success:
RateLimiter::clear($key)   // reset the counter
```

> **Why key by `user_id|ip`:** it locks out *guessing at one account from one machine*
> without punishing every user behind a shared office IP. `hit()` both records the
> attempt and returns the running count; `tooManyAttempts()` is the gate; `clear()`
> resets on success.

**How the CAPTCHA works:**
- `GET /captcha` generates a random code, **stores it in the session**, and returns
  only the **image** (the code never travels to the browser as text).
- On submit, `verify()` compares the typed value to the session code.
- It's **one-time** (forgotten on success) with a 5-minute TTL, and ambiguous glyphs
  (`0/O`, `1/I`) are excluded so humans aren't unfairly failed.

> **Livewire gotcha:** the captcha `<img>` is wrapped in `wire:ignore`. Without it,
> every Livewire re-render would re-request `/captcha`, generating a *new* code and
> invalidating the one the user just read. An Alpine button lets the user manually
> refresh the image.

**Verified:** invalid captcha rejected; the 6th attempt is blocked *before* the
password is even checked.

---

## Slice 1e — IP-lock, password expiry / first-login, and the audit trail

**What:** The remaining legacy security gates plus the login history:
1. **Per-user IP lock** (`sys_ip`) — if an IP is registered, login must come from it.
2. **Password expiry + forced first-login change** — expired/first-login users are
   sent to a change-password screen before they can use the app.
3. **Login-history audit** — every successful login writes to `login_log`.

**Files:**
- `app/Models/User.php` — `mustChangePassword()`
- `app/Http/Middleware/EnsurePasswordIsCurrent.php` — the redirect gate
- `app/Livewire/Auth/ChangePassword.php` (+ view) — the change-password screen
- `app/Events/UserAuthenticated.php` + `app/Listeners/RecordLoginHistory.php` — audit
- `app/Models/LoginLog.php` — maps `login_log`

**1) IP lock** — in `Login::login()`, right after the enabled-account gate:

```php
if (filled($user->sys_ip) && $user->sys_ip !== request()->ip()) {
    Auth::logout(); RateLimiter::hit(...); throw "authorised network only";
}
```

**2) Password expiry — why a MIDDLEWARE, not an `if` in login:**

```php
class EnsurePasswordIsCurrent {
    public function handle(Request $request, Closure $next): Response {
        $user = $request->user();
        if ($user && $user->mustChangePassword()) {
            return redirect()->route('password.change');   // block first
        }
        return $next($request);                            // else continue
    }
}
```

> **Why middleware:** if we only checked at login, a user could simply *type a URL*
> like `/dashboard` and skip the check. A middleware wraps **every protected route**,
> so there's no way around it. Crucially, the `password.change` and `logout` routes are
> left **outside** this middleware — otherwise "you must change your password" would
> redirect you to a page that also redirects you… an infinite loop.

**3) Audit via Event + Listener — why decouple it:**

```php
// In Login::login() on success:
UserAuthenticated::dispatch($user, request()->ip(), (string) request()->userAgent());

// The listener reacts:
class RecordLoginHistory {
    public function handle(UserAuthenticated $event): void {
        LoginLog::create([
            'user_id'        => $event->user->getAuthIdentifier(),
            'login_datetime' => now(),
            'sys_ip'         => $event->ip,
            'sys_os'         => $event->userAgent,
        ]);
    }
}
```

> **Why events/listeners instead of writing the log inline:** it *decouples* "a login
> happened" from "what we do about it." Tomorrow we could add a *second* listener
> (send an alert email on login from a new device) without touching the login code.
> Laravel **auto-discovers** the listener from the type-hinted `UserAuthenticated`
> argument (Laravel 11+ scans `app/Listeners`) — no manual registration. The event is
> dispatched **only after all gates pass**, so failed logins are never logged.

**Verified:** IP mismatch blocked; must-change users redirected off `/dashboard`; the
change flow updates the record and clears the requirement; wrong current-password
rejected; a `login_log` row is written on success.

---

## C. How to verify M1 yourself

```bash
php artisan test                       # run the whole suite
php artisan test --filter=LoginTest    # just the login flow
php artisan route:list                 # see the registered auth routes
php artisan tinker                     # then:  \App\Models\User::count()
```

**Result:** **18 tests / 67 assertions** pass. The 59 migrated users log in with their
existing passwords (auto-upgraded to bcrypt), behind CSRF, server-side rate limiting,
CAPTCHA, IP-lock, password expiry, forced first-login change, and a login audit trail.

---

## ✅ M1 — Auth & Access: COMPLETE

Every legacy security behaviour reproduced on a modern, testable footing, with a
zero-friction password migration. **Next: M2 — Authorization (who is allowed to do
what).**
