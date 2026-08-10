# M3 add-on — Treasury Master — Explained for Beginners

> **What this add-on gives you, in one sentence:**
> A brand-new master screen (Admin Section → Master Entry → **Treasury Master**) that
> lists treasuries — each belonging to a district — with search, pagination and Excel,
> backed by a new `treasury_master` table.

This one is special: it is the **first screen with no legacy ancestor at all**. Every
master before it (District, Bank, DDO, Location…) already existed in the old CodeIgniter
app, so we just re-skinned it. Treasury is genuinely new — which teaches two things the
others couldn't: how to add a **net-new item to a data-driven sidebar**, and why a table
*we* own can be **stricter** than a legacy one.

Read section **A (concepts)** first if any term is new.

---

## A. Concepts you need first (mini-glossary)

| Term | Plain-English meaning |
|------|----------------------|
| **Master table** | A reference list (districts, banks, treasuries…) that other data points at. |
| **Foreign key (FK)** | A DB rule: "this `dist_code` must be a real district." Blocks orphans. |
| **Data-driven sidebar** | Our menu isn't hard-coded — it is *built by reading* the `menu_items` and `permissions` tables. No row, no menu link. |
| **Seeder** | A PHP file that *inserts rows* (here: the new menu item + permission). Run with `php artisan db:seed`. |
| **Idempotent** | Safe to run many times — never creates duplicates. |
| **LGD code** | The Government-of-India Local Government Directory code for a state/district. |

---

## B. Why this slice is different

We grep-checked the legacy code: "treasury" appears only as a *job title* in employee
data — there is **no** treasury table, menu, or permission to copy. So this is a genuinely
new module: a full CRUD master modelled on Location Master (treasury → district), but with
a **search box** instead of a district-filter dropdown (your choice at the time).

---

## C. The table — stricter than the State add-on, on purpose

```php
Schema::create('treasury_master', function (Blueprint $table) {
    $table->string('treasury_code', 10)->primary();   // digit-string like "01", unique
    $table->bigInteger('dist_code');                  // NOT NULL
    $table->string('treasury_name', 150);
    $table->foreign('dist_code')->references('dist_code')->on('dist_master');
});
```

Compare this with the State add-on we did just before:

| | `dist_master.state_code` (State) | `treasury_master.dist_code` (this) |
|---|---|---|
| Existing rows? | 30 legacy districts already there | none — brand-new empty table |
| Nullable? | **Yes** (couldn't break old rows) | **No** — required from day one |
| Why | legacy table, must stay compatible | *we own it and it's empty → be strict* |

> **The lesson:** a legacy table with existing rows *forces* leniency (nullable); a new,
> empty table you own *lets* you enforce the rule immediately (NOT NULL + a real FK). Same
> project, two opposite-but-correct choices.

**Why `treasury_code` is a string, not a number.** The codes look like `01`, `02`, `11`.
If we stored them as integers, `01` would collapse to `1` and the leading zero — part of
the code — would be lost. **Rule: leading zero (or you never do maths on it) ⇒ it's a
code (string), not a number.** We still enforce *digits only* in the form
(`regex:/^[0-9]+$/`), so "numbers only" holds; it's the storage type that must be text.
This ripples into the model (`keyType = 'string'`), the `edit()`/`delete()` methods (they
take a `string $code`, not `int`), and the Blade calls (`edit('01')`, never `edit(01)`).

---

## D. Making a NEW item appear in the sidebar

Our sidebar is **data-driven**: `SidebarMenu` reads `menu_items` **joined to**
`permissions` (on `permissions.legacy_menu_id = menu_items.menu_id`), keeps only the rows
the user is allowed to see, and groups them into Section → Sub-section → Item. A link only
shows if a `menu_items` row **and** a matching `permissions` row exist.

Every other master reused a legacy `menu_items` row — Treasury has none, so we seed one:

```php
// database/seeders/TreasuryMasterMenuSeeder.php  (idempotent)
DB::table('menu_items')->updateOrInsert(['menu_id' => 237], [   // ① WHERE it lives
    'menu_label' => 'Treasury Master', 'menu' => 'adminsection', 'sub_menu' => 'masterentry',
]);
Permission::updateOrCreate(['key' => 'adminsection.treasury_master'], [   // ② the ability
    'name' => 'Treasury Master', 'group' => 'adminsection', 'legacy_menu_id' => 237,
]);
```

- **`menu_id = 237`** is one past the legacy maximum (236). Items in a sub-section sort by
  `menu_id`, so 237 puts Treasury **last** in Master Entry (after DDO) — where a newly
  added item belongs.
- **Admins see it instantly.** `hasPermissionTo()` begins with `return $this->isAdmin() ||
  …`, so any admin bypasses the check. For other roles, grant
  `adminsection.treasury_master` via the *Manage User Permissions* screen.

The last wiring bit — mapping the permission to its page — is one line in `SidebarMenu`:
`'adminsection.treasury_master' => 'master.treasuries'`.

---

## E. The rest — model, screen, export

Nothing new versus Location; it's the pattern you know:
- **`Treasury` model** — `belongsTo District`; `scopeSearch` over code + name using
  `LOWER(col) LIKE` + a direct `LIKE` on the varchar code (works on Postgres *and* the
  SQLite test DB).
- **`Treasuries` component** — uses the shared `WithCrudTable` trait; a **search box**,
  per-page dropdown, SweetAlert delete, Excel export. The form validates
  `dist_code => exists:dist_master` (belt-and-braces with the DB foreign key) and a unique
  `treasury_code`.
- **`TreasuriesExport`** — the same rows the table shows, respecting the search term.

---

## F. How to verify this add-on yourself

```bash
php artisan migrate
php artisan db:seed --class=TreasuryMasterMenuSeeder
php artisan test
```

**Result:** the full suite reached **100 passing / 283 assertions** (+10 for Treasury).
Two of those tests exist purely to lock the code format: one proves `01` is stored as
`01` (and did **not** become `1`); another proves a code with letters is rejected. Live
checks confirmed: the permission + menu row exist, the route is registered, and the admin
sidebar shows **Treasury Master → /master/treasuries**.

---

## G. Gotchas worth remembering

- **Leading-zero codes are strings.** `01` in an integer column becomes `1`. Store such
  identifiers as `varchar`, set the model `keyType = 'string'`, hint methods `string
  $code`, and quote them in Blade. Enforce digits with a regex, not an `integer` rule.
- **A new screen with no legacy origin ⇒ you must seed a `menu_items` + `permissions`
  row**, or it works by URL but never appears in the sidebar.
- **Match FK column types** (both sides `bigint`), or Postgres rejects the constraint.
- **Owned + empty table ⇒ be strict** (NOT NULL, real FK). Only go nullable when legacy
  rows force you to.

---

## ✅ Treasury Master: COMPLETE

The first net-new, data-driven sidebar screen, with a leading-zero-safe code — built
from scratch, table to menu.
