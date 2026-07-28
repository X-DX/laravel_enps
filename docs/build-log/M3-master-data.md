# M3 — Master Data (Explained for Beginners)

> **What this milestone gives you, in one sentence:**
> The first real **CRUD** screens — the reference lists (districts, banks, DDOs,
> designations, locations) and rate settings that every other module depends on —
> rebuilt as modern Livewire screens, and the reusable pattern we copy for all of them.

**CRUD** = Create, Read, Update, Delete — the four things you do to records.

Read in order: **A** (what master data is) → **B** (the legacy pattern + problems) →
**C** (concepts) → **D** (slice-by-slice, with commands) → **E** (verify).

| Slice | What | Status |
|------|------|--------|
| 3a | **District** CRUD — the reusable template | ✅ |
| 3b | Bank + Designation (standalone) | ✅ |
| 3c | Location (belongs to District — first dropdown) | ✅ |
| 3d | DDO (belongs to Location — the big one) | ✅ |
| 3e | Settings (interest rate / retirement year / share) | ✅ |

---

## A. What "Master Data" is

Reference/lookup data that everything else points at. A subscriber belongs to a **DDO**;
a DDO belongs to a **Location**; a Location belongs to a **District**. If this founda­tion
is wrong, every downstream module is wrong — which is why it comes early.

**The 8 legacy tables** (inspected live in Postgres):

| Table | Columns | Rows | Relationship |
|-------|---------|-----:|--------------|
| `dist_master` | `dist_code` (PK), `dist_name` | 30 | root |
| `loc_master` | `loc_code` (PK), `loc_name`, `dist_code` | 175 | → district |
| `ddo_master` | `ddo_code` (PK), `ddo_name`, `loc_code` | 3,085 | → location |
| `bank_master` | `bank_code` (PK), `bank_name`, `branch_name` | 25 | standalone |
| `designation_master` | `designation_id` (PK), `designation` | 1,869 | standalone |
| `rate` | `id`, `fin_year`, `rate` | 7 | interest rate per financial year |
| `retirement_year` | `year` | 1 | single setting |
| `mst_contribution_share` | `sl`, `emp_share`, `govt_share` | 1 | single setting |

---

## B. How the legacy app did it, and what was wrong

Every legacy master follows the same shape: a **controller**
(`EntrySection/District.php`) + a **model** (`DistrictModel`) + a **view** with a jQuery
**DataTable** that AJAX-calls the server. One `saveUpdateDistrictMaster()` method does
*both* add and update, decided by a hidden `add_update` field, and returns **numeric
status codes** (`'2'`=added, `'3'`=updated, `'33'`=code exists, `'4'`=name exists,
`'504'`=error).

**Problems we're fixing:**

1. **No integrity / no foreign keys** — `loc_master.dist_code` and `ddo_master.loc_code`
   are relationships in name only; nothing stops an orphan.
2. **A real type bug** — `ddo_master.loc_code` is `varchar(10)` but `loc_master.loc_code`
   is `bigint`; same key, mismatched types.
3. **No timestamps** anywhere.
4. **Codes hand-typed with a manual "does it exist?" check** instead of a DB constraint.
5. **Magic status codes** (`'33'`, `'504'`) instead of readable messages.
6. **Names force-uppercased** in PHP — a UI concern leaking into storage.

**Decisions taken for M3** (Phase A = compatibility-first, no schema changes yet):
- **Codes stay hand-typed** (they're official government codes) — but uniqueness is now a
  real DB rule + Laravel validation.
- **Names stored as-typed** (dropped the `strtoupper`), formatted in the UI.
- **No timestamps yet** (`$timestamps = false`) — added properly in Phase B (M12).
- **Real foreign keys + the varchar/bigint fix** are deferred to Phase B; for now the
  relationship is enforced in the **app** (e.g. you can't delete a district that still
  has locations).

---

## C. Concepts you need first

| Term | Plain-English meaning |
|------|----------------------|
| **CRUD** | Create, Read, Update, Delete. |
| **Livewire component** | One PHP class + one Blade view forming a reactive screen; public properties are state, public methods are actions. |
| **`WithPagination`** | A Livewire trait that adds page links + `resetPage()`. |
| **`wire:model`** | Two-way bind an input to a PHP property. `.live` = update on every keystroke; `.debounce.300ms` = wait 300 ms first. |
| **`updateOrCreate([keys],[values])`** | Find a row by `keys`; update it if found, otherwise insert — one call for both "add" and "edit". |
| **`Rule::unique(...)->ignore(...)`** | "This value must be unique — except the row we're currently editing." |
| **`wire:confirm`** | Shows a browser "Are you sure?" before running the action. |
| **`session()->flash('status', ...)`** | A one-time message shown on the next render (our success/error banners). |

---

## D. Slice 3a — District CRUD (the template), step by step

**Commands used:**

```bash
php artisan make:livewire MasterData/Districts   # scaffolds the component
# (it created a Livewire-4 single-file component; we removed it and used the
#  project's classic style: class in app/Livewire + view in resources/views/livewire)
php artisan test --filter=DistrictTest           # run just these tests
```

**The 4 files:**

### 1) `app/Models/District.php` — map onto the existing table

```php
protected $table = 'dist_master';
protected $primaryKey = 'dist_code';
public $incrementing = false;   // hand-typed code — NOT auto-assigned
protected $keyType = 'int';     // dist_code is a bigint
public $timestamps = false;     // legacy table has no created_at/updated_at
protected $fillable = ['dist_code', 'dist_name'];
```

> Same idea as `User → user_account`: we point Eloquent at a table that **already
> exists** and describe its quirks, instead of creating a new one.

### 2) `app/Livewire/MasterData/Districts.php` — the CRUD brain

The whole screen is one class. The important parts:

```php
public function save(): void {
    $this->authorize(self::ABILITY);          // re-check permission (never trust the client)
    $validated = $this->validate();           // rules() below
    District::updateOrCreate(
        ['dist_code' => $validated['dist_code']],   // find by code…
        ['dist_name' => $validated['dist_name']],   // …set the name (add OR update)
    );
    session()->flash('status', 'District saved.');
}

protected function rules(): array {
    return [
        'dist_code' => ['required','integer','min:1',
            Rule::unique('dist_master','dist_code')->ignore($this->editingCode,'dist_code')],
        'dist_name' => ['required','string','max:255'],
    ];
}
```

> **`updateOrCreate`** is the modern replacement for the legacy `add_update` flag +
> manual duplicate check. **`Rule::unique(...)->ignore(...)`** is the real DB-backed
> uniqueness rule (replacing `validateDstCodeModel()`'s `count > 0`). Validation
> messages replace the magic status codes.

**The delete guard** — protecting the integrity the legacy DB never enforced:

```php
public function delete(int $code): void {
    $this->authorize(self::ABILITY);
    if (DB::table('loc_master')->where('dist_code', $code)->exists()) {
        session()->flash('error', 'This district has locations linked to it and cannot be deleted.');
        return;                                // refuse — would orphan those locations
    }
    District::where('dist_code', $code)->delete();
}
```

> We check `loc_master` with a direct query for now; when the **Location** model
> lands (3c/3d) this becomes a clean `$district->locations()->exists()` relationship.

**Cross-database search** — because tests run on SQLite but prod is Postgres:

```php
->whereRaw('LOWER(dist_name) LIKE ?', ['%'.strtolower($term).'%'])   // works on BOTH
```

> Postgres has `ILIKE` (case-insensitive), SQLite doesn't. `LOWER(...) LIKE` behaves the
> same on both, so the same code passes tests and runs in production.

### 3) `resources/views/livewire/master-data/districts.blade.php` — the UI

A search box, a table (Code / Name / Edit / Delete), pagination, and a create/edit
panel — Tailwind + dark mode, matching the rest of the app. **No jQuery, no DataTables.**
On edit, the **code field is read-only** (it identifies the row); you only change the name.

### 4) `tests/Feature/MasterData/DistrictTest.php` — proof it works

8 tests: route is **403 without permission**, admin opens it (**200**), create, reject
blank name + duplicate code, update, delete, **refuse delete when a location links to
it**, and search filters. Like the other tests, it builds its tables in SQLite `setUp`,
so the production database is never touched.

**Wiring it in:**
- **Route** `/master/districts` (name `master.districts`) behind
  `can:adminsection.district_master`, inside the `auth` + `EnsurePasswordIsCurrent` group.
- **Sidebar** — one line added to `SidebarMenu::ROUTES`
  (`'adminsection.district_master' => 'master.districts'`), so the sidebar's **District
  Master** item is now a **live link** (previously a placeholder). This is how the menu
  lights up as modules land.

---

## E. How to verify

```bash
php artisan test --filter=DistrictTest    # 8 passed / 18 assertions
php artisan route:list --name=master      # master.districts registered
# In the app: log in as admin → sidebar → Admin Section → Master Entry → District Master
```

**Result:** slice 3a done, full suite **44 passed / 127 assertions**. The first Master
Data screen is live, and we now have a reusable CRUD template to copy for Bank,
Designation, Location, and DDO.

---

## D+. Slice 3a enhancements — per-page, SweetAlert, Excel

Added to the District screen (and therefore to the reusable template) after the first
build:

**Commands used:**

```bash
composer require maatwebsite/excel   # Laravel Excel (the tool CLAUDE.md specifies)
npm install sweetalert2              # nicer alerts/toasts/confirm dialogs
```

**1) Per-page dropdown.** A `public int $perPage = 10;` bound with `wire:model.live`,
options 10/25/50/100, and `->paginate($this->perPage)`. `updatingPerPage()` resets to
page 1 so you don't land on an empty page.

**2) SweetAlert2 notifications + delete confirm.** Wired once in `resources/js/app.js`:
- Success/error **toasts** — the component fires `$this->dispatch('notify', type: '…',
  message: '…')`; a small JS listener shows a top-right toast. (This replaced the old
  Blade flash banners.)
- **Delete confirmation** — the Delete button now opens a SweetAlert "Are you sure?"
  (via an inline Alpine `@click` calling `window.Swal`), and only calls
  `$wire.delete(code)` if confirmed — replacing the plain `wire:confirm` browser popup.

**3) Excel export.** `app/Exports/DistrictsExport.php` (Laravel Excel: `FromQuery`,
`WithHeadings`, `WithMapping`) streams the rows; the component's `export()` returns
`Excel::download(...)`. An "Excel" button in the toolbar triggers it. The export uses the
**same `District::search()` scope** as the table, so the file matches your current filter
(all matching rows, not just the visible page).

> **Teaching note — the `search` scope.** To avoid writing the search `WHERE` twice (once
> for the table, once for the export), it now lives in one place: a **query scope**
> `District::scopeSearch()`. Both the list and the export call `->search($term)`. This is
> the DRY principle — one definition, two callers.

**Tests:** +2 (per-page size, export respects the search via `Excel::fake()`) →
**10 District tests**, full suite **46 passed / 132 assertions**.

---

## Slice 3b — Bank + Designation (applying the template)

Two standalone masters built by copying the District template — the first proof that the
pattern pays off.

**Bank** (`bank_master`) — the straightforward case:
- **Hand-typed** `bank_code` (your decision), three fields (`bank_code`, `bank_name`,
  `branch_name`; the names are `varchar(30)`, so validation caps at `max:30`).
- Route `master.banks` behind `can:adminsection.bank_entry`; sidebar item lit.

**Designation** (`designation_master`) — the interesting one:
- **Auto-generated** `designation_id`. We checked the live DB first: the column has a
  Postgres **sequence** and it's **in sync** with the data (`last_value` = 1869 = table
  max), so the next insert cleanly gets 1870 — no collision.
- This changed the code shape vs the others:

  | | District / Bank (hand-typed) | Designation (auto) |
  |--|------------------------------|--------------------|
  | Model | `public $incrementing = false;` | `public $incrementing = true;` |
  | Form | has a code field | **no code field** — DB assigns it |
  | Save | `updateOrCreate([code],[…])` | `if (editing) update(); else create();` |

  > **Why the split on save?** `updateOrCreate` needs a key to match on. On a *new*
  > designation there is no id yet (the DB makes it), so "create" and "update" are
  > genuinely different paths.

**Both** inherit the 3a toolbar for free: search, per-page, SweetAlert toasts/confirms,
and Excel export (each with its own `*Export` class using the shared `search()` scope).

**A note on delete guards:** District refused deletion when locations referenced it
(a small, clear parent→child link). Bank and Designation are referenced by the *huge*
transactional tables (accounts / employees), so a synchronous "is it in use?" check on
every delete would be a performance trap — that integrity is better enforced by real
**foreign keys in Phase B**. So their deletes are plain for now (a deliberate choice, not
an oversight).

**Verification:** 14 new tests (7 each) → full suite **60 passed / 167 assertions**.
Confirmed against real Postgres data: Bank 25 rows, Designation 1,869 (search "teacher" →
80), next id 1870.

> **Refactor spotted (for later):** with 3 near-identical components, an abstract
> `MasterCrud` base is starting to look worth it. Deferring until all 5 masters exist so
> we abstract from real repetition, not a guess.

---

## Slice 3c — Location (the first *relationship*)

Location is the first master that **belongs to** another (a district), so this slice
introduces three new ideas.

**1) An Eloquent relationship (both directions).**

```php
// Location.php — a location belongs to one district
public function district(): BelongsTo {
    return $this->belongsTo(District::class, 'dist_code', 'dist_code');
}

// District.php — a district has many locations
public function locations(): HasMany {
    return $this->hasMany(Location::class, 'dist_code', 'dist_code');
}
```

> **`belongsTo` vs `hasMany`** are the two sides of the same link. The child (Location)
> *belongs to* the parent; the parent (District) *has many* children. We pass explicit
> keys because these aren't Laravel's default `district_id` names.

We also **closed the loop from 3a**: District's delete-guard now uses this relationship
(`$district->locations()->exists()`) instead of the raw query it used before the Location
model existed.

**2) A dropdown + `exists` validation.** The form has a district `<select>`, and the rule
makes sure the chosen district is real:

```php
'dist_code' => ['required', 'integer', 'exists:dist_master,dist_code'],
```

> **`exists:table,column`** is a database-backed rule: it fails validation if that value
> isn't an actual row. This is how we enforce the parent→child link in the app layer
> (until real DB foreign keys arrive in Phase B). Two failure messages: *"Select a
> district"* (required) and *"The selected district does not exist"* (exists).

**3) Avoiding N+1 with eager loading.** The table shows each location's district name.
Naively that's one extra query *per row*. So we **eager-load**:

```php
Location::query()->with('district')->search(...)->paginate(...);
```

> **N+1** = 1 query for the list + N queries (one per row) for the related district.
> `->with('district')` fetches all the districts in **one** extra query instead. The
> Excel export does the same.

**4) Filter by parent, not free-text (matches legacy).** Instead of a search box, the
Location list has a **"Select District" dropdown** (default *All districts*). Picking a
district shows only its locations — reproducing the legacy screen. Both the list and the
Excel export honour the filter:

```php
->when($this->filterDistrict !== '', fn ($q) => $q->where('dist_code', $this->filterDistrict))
```

> This "filter by parent" pattern carries straight into **3d (DDO)**, which will filter by
> **Location** the same way. (The name-search box was removed here per request; it can be
> added back alongside the dropdown if we ever want to search within a district.)

**Delete guard.** Location is itself a parent of DDO, so its delete refuses if any DDO
references it — the same small-master-child reasoning as District→Location. (Note the
legacy quirk: `ddo_master.loc_code` is `varchar(10)` while `loc_master.loc_code` is
`bigint`, so the guard compares as a string.)

**Verification:** 9 tests (incl. required-district, nonexistent-district, DDO delete
guard) → full suite **69 passed / 192 assertions**. On real data: 175 locations; location
"TAWANG" resolves to district "TAWANG".

---

## Slice 3d — DDO (the big one: cascade filter + a legacy type bug)

DDO (3,085 rows) belongs to a **location**, which belongs to a **district**. Three things
made this the hardest slice.

**1) The `varchar`/`bigint` type mismatch (a real legacy bug).** `ddo_master.loc_code` is
`varchar(10)` but `loc_master.loc_code` is `bigint`. Before writing code I checked the data:
all 3,085 loc_codes are numeric (2 blank, 1 orphan). Postgres coerces numeric strings, so
the `belongsTo` + eager loading works. But when *we* filter the varchar column by a set of
bigint location codes, we **cast to strings** to be safe on both Postgres and SQLite:

```php
$codes = Location::where('dist_code', $distCode)->pluck('loc_code')->map(fn ($v) => (string) $v);
$query->whereIn('loc_code', $codes);
```

> **Lesson:** when two columns that *should* be the same key have different types, don't
> assume the join works — check the data and control the comparison. (The proper fix — one
> type + a real FK — is a Phase B task.)

**2) A cascading District → Location filter.** With 3,085 rows and 175 locations, one flat
dropdown is unusable. So: pick a **District** (30 options) → the **Location** dropdown fills
with just that district's locations → pick one to filter the DDOs.

```php
public function updatingFilterDistrict(): void {
    $this->filterLocation = '';   // the old location no longer belongs to the new district
    $this->resetPage();
}
```

> **Dependent dropdowns in Livewire:** the Location `<select>` options are computed in
> `render()` from the chosen district, and the `updating…` hook clears the stale child
> selection. The child select is disabled until a district is picked.

**3) Nested eager loading (grandparent).** The table shows Location **and** District, so we
load two levels deep in one go — `->with('location.district')` — avoiding N+1 across both
hops. The shared `scopeForLocationFilter($distCode, $locCode)` keeps the list and the Excel
export filtering identically.

**4) Auto-generated code + a cascading *form* (per the legacy screen).** The DDO code is
**auto-generated** (its Postgres sequence is in sync, last_value = max = 3181), so — like
Designation — there is no code field; `save()` splits create-vs-update. And the **add/edit
form itself cascades**: you pick a **District** → the **Office Location** dropdown fills →
pick a location → type the DDO name. So there are now *two* independent cascades on this
screen — one for the list filter, one inside the form (`form_district` → `loc_code`, with
`updatingFormDistrict()` clearing the stale location, and edit pre-selecting the district
from the DDO's current location).

**Delete guard:** none — DDOs are referenced by the huge transactional tables, so (like
Bank/Designation) that integrity is a Phase B foreign-key job, not a per-delete query.

**Verification:** 11 tests (incl. cascade reset, filter-by-district, filter-by-location,
valid-location rule) → full suite **81 passed / 223 assertions**. On real data: 3,085 DDOs;
ddo 1 → location "TAWANG" → district "TAWANG"; the district filter finds **771 DDOs in
PAPUM PARE**.

---

## Slice 3e — Settings (a different pattern: "edit config", not CRUD)

The "Others" menu holds three configuration screens, each behind its own permission.
Two of them are **not** CRUD — there's nothing to list, add, or delete; you just load the
current value and save a new one.

**Interest Rate** (`rate`) — the one that *is* a small CRUD: one rate per financial year
(8% → 8.8% historically). Add a new year, edit an existing rate. **No delete** — a past
year's declared rate is a historical record, so erasing it would be wrong (a domain-driven
decision, not laziness). Code auto-generated (sequence in sync); `fin_year` read-only on
edit.

**Retirement Year** (`retirement_year`) and **Contribution Share** (`mst_contribution_share`)
— the **singleton "edit config" pattern**:

```php
public function mount(): void {
    $this->authorize(self::ABILITY);
    $this->year = (string) (DB::table('retirement_year')->value('year') ?? '');  // load current
}
public function save(): void {
    $this->authorize(self::ABILITY);
    $v = $this->validate();
    DB::table('retirement_year')->exists()
        ? DB::table('retirement_year')->update(['year' => $v['year']])   // update the one row
        : DB::table('retirement_year')->insert(['year' => $v['year']]);
}
```

> **Why the query builder, not an Eloquent model?** `retirement_year` has a single row and
> **no primary key** — it's a config value, not an entity with identity. Forcing a model on
> it would be awkward; `DB::table(...)->update()` on the lone row is the honest tool.
> (Contribution Share is the same, keyed by its `sl = 1` row — the NPS 10% employee /
> 14% government split, shown with a live combined-total in the UI.)

**Validation as real guard-rails:** retirement age `between:40,80`; shares `integer,
between:0,100` — so config can't be set to nonsense.

**Verification:** 7 tests (forbidden without permission, add/duplicate/blank rate,
load+update+range for retirement year and share) → full suite **89 passed / 246
assertions**. Real data intact: 7 interest rates, retirement 60, share 10/14.

---

## ✅ M3 — Master Data: COMPLETE

All five slices done (**89 tests / 246 assertions**). Five CRUD/config screens — District,
Bank, Designation, Location, DDO, plus the three Settings — all mapped onto the existing
legacy tables (Phase A, no schema changes), permission-guarded, and lighting up their
sidebar links. Along the way we built the reusable toolbar (search / per-page / SweetAlert /
Excel), relationships + `exists` validation, eager loading, cascading dependent dropdowns,
auto-generated vs hand-typed codes, and the singleton edit-config pattern.

**Deferred:** the `MasterCrud` base-class refactor (now that we have 4 near-identical CRUD
components to abstract from) — a good cleanup before or during M4. **Next: M4 — Subscriber
& Account.**

*(Open question for 3b: `designation_master.designation_id` looks like a purely-internal
counter, so it's the one master where **auto-generate** likely beats hand-typed — decide
when we build it.)*
