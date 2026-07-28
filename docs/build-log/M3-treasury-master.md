# M3 add-on — Treasury Master (explained for beginners)

> **What this is, in one sentence:**
> A brand-new master screen (Admin Section → Master Entry → **Treasury Master**) that lists
> treasuries — each one belonging to a district — with a search box, pagination and Excel,
> backed by a new `treasury_master` table.

This one is special: it's the **first screen with no legacy ancestor at all**. Every master
before it (District, Bank, DDO, Location…) already existed in the old CodeIgniter app, so we
just re-skinned it. Treasury is genuinely new — which teaches two things the others couldn't:
how to add a **net-new menu item** to a data-driven sidebar, and why a table we *own* can be
**stricter** than a legacy one.

---

## A. Mini-glossary

| Word | Plain meaning |
|---|---|
| **Master table** | A reference list (districts, banks, treasuries…) that other data points at. |
| **Foreign key (FK)** | A DB rule: "this `dist_code` must be a real district." Blocks orphans. |
| **`belongsTo`** | Eloquent for "each treasury has one parent district." |
| **Data-driven sidebar** | Our menu isn't hard-coded — it's built by reading the `menu_items` + `permissions` tables. No row, no menu link. |
| **Seeder** | A PHP file that *inserts rows* (here: the new menu + permission). Run with `php artisan db:seed`. |
| **Idempotent** | Safe to run many times — never makes duplicates. |

---

## B. Legacy vs. new

**In the legacy app:** there is **no treasury anywhere** — we grep-checked; "treasury" only
appears as a job title in employee data. So there's no table, no menu, no permission to copy.

**In the new app:** a full CRUD master, modelled on Location Master (treasury → district),
but with a **search box** instead of a district filter (your call).

---

## C. Piece 1 — the table (and why it's *stricter* than State was)

```php
Schema::create('treasury_master', function (Blueprint $table) {
    $table->string('treasury_code', 10)->primary();   // digit-string like "01", unique
    $table->bigInteger('dist_code');                  // NOT NULL
    $table->string('treasury_name', 150);
    $table->foreign('dist_code')->references('dist_code')->on('dist_master');
});
```

> **Why `treasury_code` is a string, not a number.** The codes look like `01`, `02`, `11`.
> If we stored them as integers, `01` would collapse to `1` and the leading zero — which is
> part of the code — would be lost forever. **The rule: if a value has a leading zero, or you
> will never do arithmetic on it, it is a code (string), not a number.** Same reason postal
> codes and phone numbers are strings. We still enforce *digits only* in the form
> (`regex:/^[0-9]+$/`), so "numbers only" holds — it's the *storage type* that must be text.
>
> This ripples into the code: the model uses `keyType = 'string'`, and the component's
> `edit()`/`delete()` take a `string $code` (an `int` hint would re-collapse "01" to 1), and
> the Blade calls quote the argument — `edit('01')`, never `edit(01)`.

Compare this with the State feature we did just before:

| | `dist_master.state_code` (State add-on) | `treasury_master.dist_code` (this) |
|---|---|---|
| Existing rows? | 30 legacy districts already there | none — brand-new empty table |
| Nullable? | **Yes** (couldn't break old rows) | **No** — required from day one |
| Why | legacy table, must stay compatible | *we own it and it's empty → be strict* |

> **The lesson:** *a legacy table with existing rows forces leniency; a new empty table you
> own lets you enforce the rule immediately.* Same project, two opposite-but-correct choices.

**One Postgres gotcha:** `dist_code` is `bigInteger`, not `integer`, because
`dist_master.dist_code` is a **bigint** — Postgres refuses a foreign key between mismatched
`integer` and `bigint` columns (`incompatible types`). Match the parent's type or the FK
won't build.

---

## D. Piece 2 — making a NEW item appear in the sidebar

Our sidebar is **data-driven**. `SidebarMenu` runs, roughly:

```
menu_items  ⋈  permissions        (join on legacy_menu_id)
     │
     └── keep only rows the user's permission allows
     └── group into Section → Sub-section → Item
```

So a link only shows if a `menu_items` row **and** a matching `permissions` row exist. Every
other master reused a legacy `menu_items` row — Treasury has none, so we seed one:

```php
// database/seeders/TreasuryMasterMenuSeeder.php
DB::table('menu_items')->updateOrInsert(['menu_id' => 237], [   // ① where it lives
    'menu_label' => 'Treasury Master', 'menu' => 'adminsection', 'sub_menu' => 'masterentry',
]);
Permission::updateOrCreate(['key' => 'adminsection.treasury_master'], [   // ② the ability
    'name' => 'Treasury Master', 'group' => 'adminsection', 'legacy_menu_id' => 237,
]);
```

- **`menu_id = 237`** is one past the legacy maximum (236). Items in a sub-section sort by
  `menu_id`, so 237 puts Treasury **last** in Master Entry (after DDO) — exactly where you'd
  expect a newly-added item.
- **Admins see it instantly.** `hasPermissionTo()` starts with `return $this->isAdmin() || …`,
  so any admin bypasses the check. For other roles, grant `adminsection.treasury_master` via
  the *Manage User Permissions* screen.

The last wiring bit — mapping the permission to its page — is one line in `SidebarMenu`:

```php
'adminsection.treasury_master' => 'master.treasuries',
```

---

## E. Piece 3 — the screen, the model, the export

Nothing new here versus Location — it's the pattern you know:

- **`Treasury` model** — `belongsTo District`; `scopeSearch` over code + name using
  `LOWER(col) LIKE` + `CAST(col AS TEXT) LIKE` (so it works on both Postgres and the SQLite
  test DB).
- **`Treasuries` component** — uses the shared `WithCrudTable` trait; a **search box**
  (`wire:model.live.debounce.300ms`), per-page dropdown, SweetAlert delete, Excel export.
  The form validates `dist_code => exists:dist_master` (belt-and-braces with the DB foreign
  key) and a unique `treasury_code`.
- **`TreasuriesExport`** — same rows the table shows, respecting the search term.

---

## F. Commands we ran

```bash
# 1. create the table
php artisan migrate

# 2. add the menu item + permission (so the sidebar link appears)
php artisan db:seed --class=TreasuryMasterMenuSeeder

# 3. sanity-check wiring (permission? menu row? route? admin sees it?)
php artisan tinker --execute="..."

# 4. the safety net
php artisan test
```

---

## G. Verification

```text
before this feature:  90 passed / 252 assertions
after  this feature: 100 passed / 283 assertions   (+10 Treasury tests)
```

Two of those tests exist purely to lock the code-format decision: one proves `01` is stored
as `01` (and did **not** become `1`); another proves a code with letters is rejected.

Live checks: permission row present · menu_id 237 inserted · `master.treasuries` route
registered · admin sidebar shows **Treasury Master → /master/treasuries**.

---

## H. Gotchas worth remembering

- **Leading-zero codes are strings.** `01` in an integer column becomes `1`. Store such
  identifiers as `varchar`, set the model `keyType = 'string'`, hint methods `string $code`,
  and quote them in Blade (`edit('01')`). Enforce digits with a regex, not an `integer` rule.
- **New screen with no legacy origin ⇒ you must seed a `menu_items` + `permissions` row**,
  or it will work by URL but never appear in the sidebar.
- **Match FK column types** (`bigint` ↔ `bigint`), or Postgres rejects the constraint.
- **Owned + empty table ⇒ be strict** (NOT NULL, real FK). Only go nullable when legacy rows
  force you to (as with `state_code`).
- **No delete guard yet** — nothing references a treasury today. When transactional modules
  start pointing at it, the FK (Phase B) protects integrity.
- **intelephense red squiggles are still lies** — every file passed `php -l`.

---

## ✅ Done. Treasury Master is live under Admin Section → Master Entry, after DDO Entry.
