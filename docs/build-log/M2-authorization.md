# M2 — Authorization / RBAC (Explained for Beginners)

> **What this milestone gives you, in one sentence:**
> A proper **"who is allowed to do what"** system — replacing the legacy app's
> comma-separated list of menu IDs with real database tables, and enforcing access
> in **one central place** so we can never forget a check.

This doc is written to teach. Read the sections in order:
**A** (the difference between login and permissions) → **B** (how the _legacy_ app
did it and why it was a problem) → **C** (concepts) → **D** (our new design) →
**E** (slice-by-slice, with every command) → **F** (verify it yourself).

---

## A. Authentication vs. Authorization (don't mix these up)

|                    | Question it answers                                               | Where             |
| ------------------ | ----------------------------------------------------------------- | ----------------- |
| **Authentication** | _"Are you who you say you are?"_ (correct password)               | M1                |
| **Authorization**  | _"Now that we know who you are, what are you **allowed** to do?"_ | **M2 (this one)** |

M1 got the user _through the door_. M2 decides which _rooms_ they can enter. The
industry name for what we built is **RBAC — Role-Based Access Control**.

---

## B. How the LEGACY app did authorization (and what was wrong)

The old CodeIgniter app stored each user's permissions as a **single comma-separated
string** in a column called `menu_ids`.

**The pieces in the legacy DB:**

- `user_account.role_flag` — one letter: `A` = Admin, `S` = Staff, `U` = User.
- `user_account.menu_ids` — a text field like `"1,5,12,171"`. Each number is the ID
  of a menu/feature the user may use.
- `menu_items` — a lookup table; each row is one feature
  (`menu_id`, `menu_label`, `menu`, `sub_menu`).

**How it checked a permission** (real legacy code):

```php
// application/models/ValidateMenu.php
function validateFun($menu_id){
    $role_flag = $this->session->userdata('role_flag');
    $menu_flag = $this->session->userdata($menu_id);      // read a flag from the SESSION
    if($role_flag == "A"){
        return TRUE;                                       // admins can do anything
    }else if(($role_flag == "S" || $role_flag == "U") && $menu_flag == "1"){
        return TRUE;                                       // staff/user: only if flagged
    }else{
        redirect('Enps');                                  // otherwise, bounce them away
    }
}
```

And at login, the CSV was **exploded and copied into the session** (real legacy code):

```php
// application/controllers/Auth.php
function setAssignedMenus($assigned_menus){
    $token = strtok($assigned_menus, ",");    // split "1,5,171" on commas
    while ($token != false){
        $this->session->set_userdata(array($token.'m' => '1'));  // e.g. session["5m"]="1"
        // ...also flags the menu + sub_menu names...
        $token = strtok(",");
    }
}
```

**So the legacy flow was:** login → read the `menu_ids` string → dump a bunch of `"1"`
flags into the session → every controller method calls `validateFun()` which reads
those session flags.

### Why that was a problem (this is the "why we changed it")

1. **Data is not queryable.(Permission stored as text)** Permissions live inside a text blob (`"1,5,171"`). You
   **cannot** ask the database simple questions like _"who can issue accounts?"_ You'd
   have to fetch every user and string-search their CSV. No foreign keys, no integrity.
2. **Orphans pile up.** Delete a menu and every `menu_ids` string still holds its old
   number. Nothing cleans them up.
3. **Stale permissions.** Because permissions are **copied into the session at login**,
   changing a user's access does nothing until they log **out and back in**. An admin
   revoking access can't do it _now_.
4. **Easy to forget a check.** `validateFun()` must be pasted at the top of **every**
   controller action by hand. Miss one → that page is wide open. Security by
   copy-paste.
5. **No real roles.** `role_flag` is just a coarse A/S/U label. There's no notion of
   "all Staff get these 10 permissions by default" — every staff user is assigned
   every menu individually.
6. **Wrong failure behaviour.** On denial it `redirect()`s instead of returning a
   proper `403 Forbidden`.

---

## C. Concepts you need first (mini-glossary)

| Term                   | Plain-English meaning                                                                                                                                                |
| ---------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **RBAC**               | Role-Based Access Control: users have **roles** and/or direct **permissions**; the app checks permissions before letting an action run.                              |
| **Migration**          | A PHP file that **creates or changes database tables** in code (version-controlled), instead of clicking around in a DB tool. Run with `php artisan migrate`.        |
| **Pivot / join table** | A table that connects two others in a **many-to-many** relationship. `role_permission` connects roles ↔ permissions; `user_permission` connects users ↔ permissions. |
| **Many-to-many**       | "A role has many permissions, and a permission belongs to many roles." Expressed in Eloquent with `belongsToMany()`.                                                 |
| **Seeder**             | A class that **fills tables with data**. Ours does a one-time migration of the legacy CSV into the new tables. Run with `php artisan db:seed`.                       |
| **Gate**               | Laravel's authorization checker. `Gate::allows('key')` answers "is the current user allowed?"                                                                        |
| **`Gate::before`**     | A hook that runs **before every** gate/permission check — the single place we plug our RBAC in.                                                                      |
| **Policy**             | A class holding authorization rules for a specific model (e.g. "can this user edit _this_ subscriber?"). We don't need one yet, but our design leaves room for them. |
| **`can:` middleware**  | A route filter: `->middleware('can:some.key')` blocks the request with a 403 unless the user holds `some.key`.                                                       |
| **`@can` (Blade)**     | A template directive: `@can('key') ... @endcan` shows HTML only if the user is allowed.                                                                              |
| **Memoise**            | Compute something once per request and reuse it, to avoid repeat DB queries.                                                                                         |

---

## D. Our new design (the picture)

We replaced the CSV with **four small, normalised tables**:

```text
      roles                     permissions
   ┌──────────┐              ┌────────────────────┐
   │ id       │              │ id                 │
   │ code (A/S/U)            │ key  (e.g.         │
   │ name     │              │   entrysection.    │
   └────┬─────┘              │   issue_account)   │
        │                    │ name / group       │
        │                    │ legacy_menu_id ────┼──► maps back to menu_items.menu_id
        │                    └─────────┬──────────┘
        │                              │
        │   role_permission            │      user_permission
        │  ┌───────────────┐           │     ┌──────────────────┐
        └──┤ role_id       │           └─────┤ permission_id     │
           │ permission_id ├── (m:m) ────────┤ user_id (varchar) │──► user_account.user_id
           └───────────────┘                 └──────────────────┘
        "defaults for a role"            "granted directly to one user"


-------------- OR -------------------------
                                 roles
                  ┌─────────────────┐
                  │ id              │
                  │ code = S        │
                  │ name = Staff    │
                  └────────┬────────┘
                           │
                           │
                           │ role.id
                           │
                           ▼

                 role_permission
             ┌─────────────────────┐
             │ role_id             │
             │ permission_id       │
             └──────────┬──────────┘
                        │
                        │
                        ▼


user_account                              permissions
┌─────────────────┐                     ┌──────────────────────────┐
│ user_id = ado   │                     │ id = 10                  │
│                 │                     │ key =                    │
│ role_flag = S   │                     │ entrysection.issue       │
│                 │                     │                          │
│ menu_ids(old)   │                     │ legacy_menu_id = 1       │
└────────┬────────┘                     └───────────┬──────────────┘
         │                                          ▲
         │                                          │
         │                                          │
         │             user_permission             │
         │        ┌────────────────────┐           │
         └───────►│ user_id = ado      │───────────┘
                  │ permission_id=10   │
                  └────────────────────┘

```

**The rule for "is a user allowed?"**

```text
effective permissions  =  (permissions from the user's ROLE)  ∪  (their DIRECT grants)
Admin (role_flag 'A')  =  allowed EVERYTHING (bypass)
```

We faithfully mirrored the legacy model (which was **per-user** grants), so today
`role_permission` is empty and everyone's access comes from `user_permission` — but the
_structure_ now supports role-defaults for free when we want them.

**How this fixes every legacy problem:**

- Queryable, with foreign keys and integrity ✔ (problems 1 & 2)
- Read **fresh from the DB each request** → admin edits take effect immediately ✔ (3)
- Enforced in **one** `Gate::before` + reusable `can:` middleware → impossible to
  "forget" ✔ (4)
- Real role table ready for defaults ✔ (5)
- Returns a proper **403** ✔ (6)

---

## E. Slice by slice — what we built, with every command

### Slice 2a — The four tables + the models

**Goal:** create the RBAC tables and the Eloquent models that read them.

**Commands used:**

```bash
# One migration per table (creates empty files in database/migrations/):
php artisan make:migration create_roles_table
php artisan make:migration create_permissions_table
php artisan make:migration create_role_permission_table
php artisan make:migration create_user_permission_table

# Two new models:
php artisan make:model Role
php artisan make:model Permission

# After filling the migration files in, actually build the tables:
php artisan migrate
```

> **Tip:** `php artisan make:model Role -m` would create the model **and** a migration
> in one go (`-m` = "with migration"). We made them separately here.

**What a migration file looks like** (`permissions` table):

```php
Schema::create('permissions', function (Blueprint $table) {
    $table->id();                                            // auto-increment primary key
    $table->string('key')->unique();                        // e.g. entrysection.issue_account
    $table->string('name');                                 // human label
    $table->string('group')->nullable();                    // legacy top-level menu
    $table->unsignedBigInteger('legacy_menu_id')->nullable()->index();  // link back to menu_items
    $table->timestamps();                                   // created_at / updated_at
});
```

The **pivot** `user_permission` deliberately has **no foreign key on `user_id`**:

```php
$table->string('user_id', 10);                              // matches user_account.user_id
$table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
$table->primary(['user_id', 'permission_id']);              // a user can't hold the same perm twice
```

> **Why no FK on `user_id`?** `user_account` is a _legacy_ table we don't own in
> Phase A. Not adding the FK keeps our new tables decoupled from the legacy schema and
> keeps tests simple. `cascadeOnDelete` on `permission_id` means: delete a permission
> and its grant rows vanish automatically.

**The models describe the relationships** (this is where many-to-many lives):

```php
// app/Models/Role.php
public function permissions(): BelongsToMany {
    return $this->belongsToMany(Permission::class, 'role_permission');
}
```

```php
// app/Models/User.php  (the important RBAC methods)
public function isAdmin(): bool {
    return $this->role_flag === 'A';                        // admins bypass everything
}

public function hasPermissionTo(string $key): bool {
    return $this->isAdmin() || $this->permissionKeys()->contains($key);
}

public function permissionKeys(): Collection {
    if ($this->permissionKeyCache !== null) {               // already computed? reuse it
        return $this->permissionKeyCache;
    }
    $direct = $this->directPermissions()->pluck('key');     // their own grants
    $viaRole = $this->role ? $this->role->permissions->pluck('key') : collect();
    return $this->permissionKeyCache = $direct->merge($viaRole)->unique()->values();
}
```

> **Why `permissionKeyCache` (memoising) matters:** without it, every single
> `hasPermissionTo()` call would hit the database. The sidebar alone checks ~89 items.
> We load the permission list **once per request** and reuse it.

> **The `first migration against Postgres` note:** we also **removed the 3 stock
> scaffold migrations** (`users`, `cache`, `jobs`). We don't use a `users` table (we
> use `user_account`), and cache/jobs tables come later (M5). After this,
> `php artisan migrate` adds only our 4 RBAC tables — the 46 migrated legacy tables are
> untouched.

**Verified:** 23 tests passing — admin bypass works, a direct grant is allowed, an
ungranted key is denied.

---

### Slice 2b — The seeder (move legacy data into the new tables)

**Goal:** fill `roles`, `permissions`, and `user_permission` from the real legacy data,
**once**, and prove nothing was lost.

**Commands used:**

```bash
php artisan make:seeder LegacyRbacSeeder      # creates database/seeders/LegacyRbacSeeder.php
php artisan db:seed --class=LegacyRbacSeeder  # run just this seeder
```

**What it does, in plain steps** (`LegacyRbacSeeder::run()`):

```php
DB::transaction(function () {          // all-or-nothing: if anything fails, nothing is saved
    $this->clearExisting();            // wipe the 4 tables first → safe to re-run (idempotent)
    $this->seedRoles();                // 1) roles  ← distinct role_flag values (A/S/U)
    $map = $this->seedPermissions();   // 2) permissions ← one per menu_items row
    $this->seedUserGrants($map);       // 3) user_permission ← each user's menu_ids CSV
});
$this->report();                       // print counts + run a PARITY CHECK
```

- **Roles:** `SELECT DISTINCT role_flag` → insert `A`→Administrator, `S`→Staff, `U`→User.
- **Permissions:** one row per `menu_items` record. The legacy `menu_name` column is
  **blank** on every row, so the **key** is built from the section + a slug of the
  label, e.g. `entrysection.issue_account`. We keep `legacy_menu_id` so we can join back.
- **Grants:** for each user, split their `menu_ids` CSV, map each number through
  `legacy_menu_id` → new `permission_id`, and insert. **Blanks, duplicates, and unknown
  menu IDs (deleted menus) are skipped.**

**The clever safety net — the parity check** (why you can trust the migration):

```php
// For every user: the number of grants we created MUST equal the number of
// DISTINCT, VALID menu_ids they had. If even one user mismatches, it warns.
```

**Verified on real data:**

```text
Seeded 3 roles, 89 permissions, 2244 grants across 58 users.
Parity check passed: every user's grants match their valid menu_ids.
```

> **Idempotent** = you can run it again and again and get the same result (it clears
> first, then rebuilds). This is important for a data migration you might re-run.

```text

                LegacyRbacSeeder
                       |
        +--------------+--------------+
        |              |              |
 user_account      menu_items       role_flag
        |              |              |
        v              v              v
user_permission   permissions       roles


```

---

### Slice 2c — Enforcement (the single most important slice)

**Goal:** actually _enforce_ the permissions — in **one** place — so every part of the
app (routes, controllers, templates) can ask "is this user allowed?" the same way.

**Command used:**

```bash
php artisan make:provider AuthServiceProvider   # a "startup script" to register the rule
# then register it in bootstrap/providers.php
```

**The entire enforcement is these few lines** (`AuthServiceProvider::boot()`):

```php
Gate::before(function (?User $user, string $ability) {
    if (! $user)            return null;    // guest → let the route middleware reject
    if ($user->isAdmin())   return true;    // admin → allowed everything
    return $user->hasPermissionTo($ability) ? true : null;
});
```

**How to read this:** `Gate::before` runs **before every** authorization check in the
whole app. For any ability string (like `entrysection.issue_account`):

- a **guest** gets `null` (meaning "no opinion" → the login middleware handles it),
- an **admin** gets `true` (bypass),
- everyone else is allowed **only if** they hold that permission; otherwise `null`.

> **Why return `null` and not `false` for "no permission"?** Returning `null` means
> _"I have no opinion, let someone else decide."_ This lets model **Policies** we add
> later still get a say. Returning `false` would hard-deny and block them. An ability
> with no policy and no grant naturally ends up denied anyway.

**What this one hook unlocks — three idiomatic ways to check access, for free:**

```php
// 1) On a route (returns 403 automatically if not allowed):
Route::get('/admin/permissions', ManageUserPermissions::class)
     ->middleware('can:adminsection.add_update_user');

// 2) In PHP (throws a 403 if not allowed):
$this->authorize('adminsection.add_update_user');

// 3) In a Blade template (show/hide UI):
@can('entrysection.issue_account')
    <a href="...">Issue Account</a>
@endcan
```

> **Why one `Gate::before` instead of defining all 89 permissions at boot?** Defining
> 89 gates on every request would query the DB constantly. Our hook calls
> `hasPermissionTo()`, which lazy-loads + memoises → **at most one query per request.**

**Verified:** 30 tests. `can:` middleware returns **200** for a holder and **403** for
a non-holder. Admin allows anything; staff `ado` is allowed its own permission and
denied an admin-only one.

```text

USER REQUEST


ado opens page
        |
        v
Route Middleware

can:entrysection.issue_account
        |
        v
Gate::before
        |
        +--------------------+
        |
   Is user logged in?
        |
      YES
        |
        v
   Is Admin?
        |
   +----+----+
   |         |
 YES         NO
 |           |
ALLOW       Check Permission
             |
             v
        permissionKeys()
             |
   role_permission
          +
   user_permission
             |
             v
   Does user have ability?

        +----+----+
        |         |
       YES        NO
        |         |
      200        403

```

---

### Slice 2d — The permission-driven sidebar

**Goal:** the navigation menu should show a user **only the items they're allowed to
use** — automatically.

**No new artisan command** — this is a plain PHP helper + a Blade view:

- `app/Support/Navigation/SidebarMenu.php` — builds the menu tree
- `resources/views/components/app/sidebar.blade.php` — renders it

**How it works:** it joins `menu_items` → `permissions` (on `legacy_menu_id`), then
**keeps only the items where `$user->hasPermissionTo($key)` is true**, and arranges
them as the legacy 3-level tree (Section → Sub-section → Item). Admins (who bypass)
see everything.

```php
foreach ($rows as $r) {
    if (! $user->hasPermissionTo($r->permission_key)) {
        continue;                         // hide anything they can't access
    }
    // ...group into Section → Sub-section → Item...
}
```

> Because `hasPermissionTo` is memoised, filtering ~89 items is **one** permission load
> plus in-memory checks — not 89 queries.

**Verified:** admin sees all sections/items; staff `ado` sees only their 12 items —
exactly their permissions.

_(This slice was later refined into the exact legacy 3-level tree with open/closed
colour states — see the "Sidebar refinement" note at the bottom.)_

```text

USER LOGIN

ado
   |
   v
OPEN DASHBOARD
   |
   v
sidebar.blade.php
   |
   v
SidebarMenu.php
   |
   v
Load menu_items
JOIN
permissions
   |
   v
89 menu items
   |
   v
Loop starts
   |
   v
hasPermissionTo(key)
   |
   v
permissionKeys()
   |
   +------------------------------+
   |                              |
Cache exists?                  Cache NULL?
   |                              |
  YES                            NO
   |                              |
   v                              v
Use memory                 Query Database
    |                           |
    |                           +------------+
    |                           |            |
    |                           v            v
    |                    user_permission   role_permission
    |                           |            |
    |                           +-----+------+
    |                                 |
    |                                 v
    |                          Merge permissions
    |                                 |
    |                                 v
    |                          Save to cache
    |                                 |
    |                                 v
    |                          Return list
    |
    |
    v
Does key exist?
       +-----------+
       |           |
      YES          NO
       |           |
 Show menu      Hide menu

```

---

### Slice 2e — The admin screen to grant/revoke permissions

**Goal:** give admins a UI to change what a user can access — the modern replacement
for the legacy "assign menus" multi-select.

**Command used:**

```bash
php artisan make:livewire Admin/ManageUserPermissions
# creates:
#   app/Livewire/Admin/ManageUserPermissions.php   (the PHP component)
#   resources/views/livewire/admin/manage-user-permissions.blade.php  (the view)
```

**How it works** (`ManageUserPermissions`):

```php
private const ABILITY = 'adminsection.add_update_user';

public function mount(): void {
    $this->authorize(self::ABILITY);        // block non-admins from even opening it (403)
}

public function updatedUserId(): void {     // runs automatically when you pick a user
    $this->selected = User::find($this->userId)?->directPermissions()
        ->pluck('permissions.id')->map(fn ($id) => (string) $id)->all() ?? [];
    // → tick the checkboxes for permissions they already have
}

public function save(): void {
    $this->authorize(self::ABILITY);        // re-check on save (never trust the client)
    User::findOrFail($this->userId)
        ->directPermissions()
        ->sync($this->selected);            // sync() = add the new, remove the unchecked
    $this->saved = true;
}
```

> **`sync()` is the magic verb** for pivot tables: give it the list of IDs that _should_
> be checked, and it inserts the missing ones and deletes the rest — no manual diffing.

> **Livewire lifecycle note:** the method name `updatedUserId()` is a convention —
> Livewire calls it automatically whenever the public property `$userId` changes. That's
> how selecting a user instantly loads their current grants without a page reload.

**Where it lives** (`routes/web.php`) — protected two ways (belt and braces):

```php
Route::get('/admin/permissions', ManageUserPermissions::class)
    ->middleware('can:adminsection.add_update_user')   // route-level guard (403)
    ->name('admin.permissions');
```

...**and** `authorize()` inside `mount()`/`save()`. This is the sidebar's **first live
link** — everything else stays a placeholder until its module is built.

**Verified:** 36 tests. Admin opens the screen (200); a non-admin gets 403;
grant/revoke via `sync` works.

```text

ADMIN
  |
  v
/admin/permissions
  |
  v
Route
can:adminsection.add_update_user
  |
  v
Gate::before
  |
  v
Allowed?
  |
 YES
  |
  v
ManageUserPermissions.php
  |
  v
mount()
authorize()
  |
  v
Load screen
  |
  v
Admin selects ado
  |
  v
updatedUserId()
  |
  v
Read:
user_permission
  |
  v
Tick checkboxes
  |
  v
Admin modifies
  |
  v
SAVE
  |
  v
authorize again
  |
  v
sync()
  |
  v
Update:
user_permission

```

---

## F. How to verify M2 yourself

```bash
php artisan migrate                              # build the 4 tables
php artisan db:seed --class=LegacyRbacSeeder     # migrate legacy data (prints parity check)
php artisan test                                 # run the whole suite (36 passing)
php artisan tinker                               # then, live-check the model:
#   > $u = App\Models\User::find('ado');
#   > $u->hasPermissionTo('entrysection.issue_account');   // true/false
#   > $u->permissionKeys();                                 // the full list
php artisan route:list                           # see the can:-protected admin route
```

**Result:** **36 tests / 106 assertions** pass. The legacy CSV `menu_ids` ACL is fully
replaced by a real roles/permissions model (3 roles, 89 permissions, 2,244 grants,
parity-checked), enforced by a single `Gate::before` (admin bypass + `can:`/`@can`/
`authorize`), driving a permission-filtered sidebar, and manageable from an admin
screen.

---

## ✅ M2 — Authorization: COMPLETE. **Next: M3 — Master Data.**

---

## Appendix — Sidebar refinement (legacy 3-level tree + open/closed colours)

After 2d, the sidebar was reworked from a flat list into the **exact legacy 3-level
tree**: Section → Sub-section → Item (e.g. _Entry Section → Account Register → Issue
Account_).

- **Names + structure come from the legacy `staff_menu.php`** (they aren't in the data):
  a `SECTIONS` map (menu code → section name) and a `SUBSECTIONS` map
  (`menu|sub_menu` → sub-section name). Several menu codes (export, reexport,
  accountinterest, closebalance) display **under "Admin Section"**, and titles use the
  exact legacy wording ("Entry Section", not "Entry").
- **Nested `<details>`** (native HTML collapse, no JavaScript). The Section + Sub-section
  containing the current page **auto-open**, and the active item is **highlighted**.
- **Open vs closed colour differentiation** (both light & dark themes): an open Section
  gets an indigo left-accent + tint and indigo text; an open Sub-section turns indigo;
  the chevron tints too — so you can always see which branch is expanded. Implemented
  with Tailwind's `group-open` variant.
- **Active detection uses `request()->routeIs()`** (route-name based, host-independent),
  so it works whether you browse via `localhost` or `127.0.0.1`.
- Files: `app/Support/Navigation/SidebarMenu.php`,
  `resources/views/components/app/sidebar.blade.php`,
  `tests/Feature/Auth/SidebarMenuTest.php`. Verified: **36 tests / 109 assertions**; the
  real admin tree matches the legacy.
