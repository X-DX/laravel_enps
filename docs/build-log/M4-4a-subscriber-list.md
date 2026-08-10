# M4 · Slice 4a — Subscriber List ("View All Accounts") — Explained for Beginners

> **What this slice gives you, in one sentence:**
> A read-only screen that lists every **subscriber** (all 33,594 of them) from the
> legacy `allotment_accnt_no` table — searchable, filterable by status, with the
> PRAN and a coloured status badge, and an Excel download.

This is the **first screen of M4** (Subscriber & Account), and it is deliberately
**read-only** — it only *shows* data, it never changes it. We start read-only so we
can get the data model and the relationships right before we ever risk writing.

If you're new to this part, read section **A (concepts)** first, then **B (the big
picture)**, then the build sections.

---

## A. Concepts you need first (mini-glossary)

| Term | Plain-English meaning | Where we use it |
|------|----------------------|-----------------|
| **Subscriber** | A government employee enrolled in NPS. One row of `allotment_accnt_no` = one subscriber, holding their details *and* their allotted account number. | `App\Models\Subscriber` |
| **Model (Eloquent)** | A PHP class that stands for one database table. Reading `Subscriber::find(1)` gives you row 1 as an object. | `Subscriber`, `Department`, `PranNo` |
| **Relationship** | A link between two tables expressed in code. "A subscriber *belongs to* a DDO" means the subscriber row stores the DDO's key, and Eloquent can fetch the DDO for you. | `ddo()`, `designationMaster()`, `pran()` |
| **Eager loading** | Fetching related rows *up front, in one query*, instead of one query per row. Prevents the "N+1 problem" (1 query for the list + N queries for each row's DDO). | `->with([...])` |
| **Query scope** | A reusable piece of a query you keep on the Model and call by name, so the list and the export filter *identically*. | `scopeFilter()` |
| **Pagination** | Showing the data in pages (25 rows at a time) instead of all 33,594 at once. | `WithPagination` |
| **`save_flag`** | The legacy status letter on each subscriber: `T` = Temporary (a draft), `F` = Finalized (issued). | the Status filter/badge |

---

## B. The big picture — how the screen produces a page

```text
Browser opens /accounts
        │
        ▼
Route (accounts.index) → checks permission "entrysection.view_all_accounts"
        │
        ▼
Livewire component  App\Livewire\Accounts\Subscribers
   • public $search   ← the search box
   • public $status   ← the All/Pending/Finalized dropdown
   • public $perPage  ← rows per page (25)
        │
        ▼
render() builds the query:
   Subscriber::query()
     ->with(['ddo','designationMaster','pran'])   ← load the linked rows in one go
     ->filter($search, $status)                    ← the shared scope (search + status)
     ->orderBy('id')
     ->paginate($perPage)
        │
        ▼
The Blade view draws the table, one row per subscriber:
   Sl · Account No (or "pending") · PRAN · Name · DOB · Dept Code · Department ·
   Designation · DDO · Status (green Finalized / amber Pending)
```

One extra query loads the 29 departments once (as a small `[code => name]` lookup),
because of a quirk explained below.

---

## C. The models (the foundation)

We created three Eloquent models. Each maps onto one legacy table.

### `Subscriber` → `allotment_accnt_no`

```php
protected $table = 'allotment_accnt_no';
public $timestamps = false;   // legacy table has no created_at / updated_at
```

`id` is a normal auto-increment primary key, so we don't need the "hand-typed key"
flags here. We add **casts** so a few columns come back as convenient types:

```php
protected $casts = [
    'dob' => 'date', 'doj' => 'date', 'isactive' => 'boolean',
];
```
> **Why cast a date?** The database stores a date as the text `"1981-01-11"`. Casting
> it to `date` turns it into a *date object*, so in the view we can call
> `->format('d-m-Y')` and print `11-01-1981`. Without the cast it's just a string.

**The relationships:**

```php
public function ddo(): BelongsTo          // ddocode → ddo_master.ddo_sl
public function designationMaster(): BelongsTo   // designation → designation_master.designation_id
```

> **Why `designationMaster` and not `designation`?** The table has a *column* literally
> called `designation` (it holds the designation's id). If we named the relationship
> `designation()` too, `$sub->designation` would be ambiguous — Eloquent would return
> the column (a number) and hide the relationship. So we give the relationship a
> different name, `designationMaster()`, and read the name with
> `$sub->designationMaster->designation`. (`ddocode` vs `ddo()` has no such clash, so
> that one stays clean.)

### `Department` → `department` (a tiny 29-row lookup)

```php
protected $primaryKey = 'dept_code';
public $incrementing = false;      // dept_code is a hand-known code like "01"
protected $keyType = 'string';
```

> **Why no `department()` relationship on `Subscriber`?** The subscriber stores the
> department in a column called `nameofdept`, but it's a legacy `char` type, so it's
> **padded with spaces** — stored as `"01 "` not `"01"`. Eloquent's `belongsTo`
> matches the *raw* value, and `"01 "` would never equal `department.dept_code = "01"`.
> Since the department table is tiny (29 rows), we sidestep this by loading it once as
> a `[dept_code => dept_name]` map and looking it up with `trim($sub->nameofdept)`.

### `PranNo` → `pran_no`

```php
public function pran(): HasOne   // on Subscriber: pran_no.account_no = account_no
```
> **Why `hasOne` and not a lookup map?** Each subscriber has *one* PRAN, linked by the
> account number. But `pran_no` has 33,000 rows — far too many to load all at once
> like we did for departments. So we link it like a normal relationship and eager-load
> just the current page's PRANs.

---

## D. The component (the brain)

`App\Livewire\Accounts\Subscribers` uses `WithPagination` **directly** — not our
`WithCrudTable` trait.

> **Why not the CRUD trait?** That trait was built for the master *editing* screens; it
> carries an add-form flag and a toast helper we don't need here, and its page size is
> fixed at 10. This screen is read-only and wants 25 rows a page, and PHP won't let a
> class re-declare a trait's property with a different default. So plain `WithPagination`
> is the honest fit.

The three properties (`$search`, `$status`, `$perPage`) each have an `updating…()` hook
that jumps back to page 1 when they change (so you don't get stuck on an empty page 5).

**The search + status live in one shared place** — a query scope on the model:

```php
public function scopeFilter(Builder $query, string $search, string $status): Builder
{
    return $query
        ->when($search !== '', function ($q) use ($search) {
            $term = '%'.strtolower($search).'%';
            $q->where(function ($q) use ($term) {
                $q->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(account_no) LIKE ?', [$term]);
            });
        })
        ->when($status !== '', fn ($q) => $q->where('save_flag', $status));
}
```

> **Why a scope?** Both the on-screen list **and** the Excel export must filter the same
> way. Writing the logic twice invites them to drift apart. A scope defines it once, and
> both call `->filter($search, $status)`.
>
> **Why `LOWER(col) LIKE` and not `ILIKE`?** `ILIKE` (case-insensitive search) is a
> Postgres-only feature. Our tests run on SQLite, which has no `ILIKE`. `LOWER(col) LIKE`
> works on both, so the same code passes tests and runs in production.

---

## E. The view (the table)

The Blade view is a plain table. Two cells are "smart":

- **Account No** — if `save_flag = 'T'` (a draft), we show an amber **"pending"** badge
  instead of a number, because a draft hasn't been allotted an account number yet.
- **Status** — a green **"Finalized"** badge for `F`, an amber **"Pending"** badge for `T`.

The **Sl** (serial) column is a running number that respects the page:
`{{ $subscribers->firstItem() + $loop->index }}` → 1,2,3… on page 1, then 26,27… on
page 2.

**One screen replaces three legacy screens.** The old app had *separate* menu items
for "View All", "Pending Issue", and "Finalized Issue" accounts. We collapse all three
into one screen with a **Status** dropdown (All / Pending / Finalized), which just adds
`where save_flag = …` when a value is chosen.

---

## F. Wiring: route, permission, sidebar

```php
Route::get('/accounts', Subscribers::class)
    ->middleware('can:entrysection.view_all_accounts')
    ->name('accounts.index');
```

The permission `entrysection.view_all_accounts` is an **existing** legacy permission
(menu item 154) — we did **not** invent a new one. Admins pass it automatically (they
bypass every permission check). We added one line to `SidebarMenu::ROUTES` so the
sidebar's "View All Accounts" entry becomes a live link.

---

## G. How to verify 4a yourself

```bash
php artisan test --filter=SubscriberTest   # just this screen's tests
php artisan test                            # the whole suite
```

**Result:** 8 feature tests; the full suite went from **104 → 112 passing / 319
assertions**. The tests cover: the permission gate, listing all the detail columns
(including the department via the trimmed code and the PRAN), the "pending" display for
drafts, search by name and by account number, the status filter, and the filtered Excel
export.

---

## H. Gotchas worth remembering

- **The `char` padding trap.** `nameofdept` is stored as `"01 "`. Always `trim()` legacy
  `char` columns before comparing or looking them up.
- **The column-vs-relationship name clash.** A relationship can't share a name with a
  real column (`designation`), or the column wins. Rename the relationship.
- **Small lookup → map; big lookup → relationship.** 29 departments = load once as a map.
  33,000 PRANs = a normal eager-loaded relationship.
- **One scope, two consumers.** The list and the export share `scopeFilter` so they can
  never disagree.

---

## ✅ 4a — Subscriber list: COMPLETE

A fast, searchable, read-only register of every subscriber, with status filtering and
Excel export. **Next: 4b — the detail page for one subscriber.**
