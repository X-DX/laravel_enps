# M4 · slice 4a — Subscriber list ("View All Accounts")

> **What this is, in one sentence:**
> A read-only, searchable register of every subscriber (`allotment_accnt_no`) — the first
> screen of M4, the Subscriber & Account module.

Built hand-typed, one concept at a time (the user types the feature code; tests + git are
mine). No writes yet — 4a just lets us browse the 33,594 subscribers safely.

---

## A. What a subscriber is

A **subscriber** is a government employee enrolled in NPS. One row of `allotment_accnt_no`
holds their details **and** their allotted account number (format `AP/NPS/{dept}/{seq}`).

Lifecycle: an entry starts as a **draft** (`save_flag = 'T'`, no account number). When it's
**finalized** it becomes `save_flag = 'F'` and the account number is generated. So in the
list, a `T` row shows **"pending"** instead of a number.

---

## B. The models

| Model | Table | Notes |
|---|---|---|
| `Subscriber` | `allotment_accnt_no` | auto-increment `id`; no timestamps; casts `dob`/`doj`/`isactive` |
| `Department` | `department` | tiny 29-row reference (`dept_code`, `dept_name`) |
| `PranNo` | `pran_no` | one PRAN per account |

**Relationships on `Subscriber`:**
- `ddo()` → `belongsTo(Ddo, 'ddocode', 'ddo_sl')`
- `designationMaster()` → `belongsTo(Designation, 'designation', 'designation_id')`
- `pran()` → `hasOne(PranNo, 'account_no', 'account_no')`

Two naming gotchas worth remembering:
1. **Column/relationship name clash.** The table has a `designation` *column* (the foreign
   key). A relationship also named `designation()` would be shadowed by the column, so it's
   `designationMaster()`.
2. **`department` is not a `belongsTo`.** `nameofdept` is stored padded (`"01 "`) as a legacy
   `char`, while `department.dept_code` is `"01"` — `belongsTo` matches raw values, so they
   wouldn't line up. Since the table is tiny, we load `[dept_code => dept_name]` once and look
   up `trim(nameofdept)`.

---

## C. The screen

`App\Livewire\Accounts\Subscribers` (uses `WithPagination` directly — read-only, so no CRUD
trait). One list replaces the legacy's three screens (View All / Pending / Finalized) via a
**status dropdown**. Search covers name + account number (`LOWER(col) LIKE`, cross-DB).

Columns: **Sl · Account No · Pran No · Name · DOB · Dept Code · Department · Designation · DDO · Status**.
- **Account No / Status:** `F` → the number + a green "Finalized" badge; `T` → "pending" + an
  amber "Pending" badge.
- **PRAN** is stored as `double precision`, so it's printed with `number_format(..., 0, '.', '')`
  to avoid a big number showing as `1.1E+11`.

Route `accounts.index` (`/accounts`) behind the **existing** legacy permission
`entrysection.view_all_accounts` — no new permission needed. `SidebarMenu::ROUTES` maps it, so
"View All Accounts" lights up under Entry Section → Account Register.

A shared query scope `Subscriber::scopeFilter($search, $status)` is used by **both** the list
and the Excel export, so they always agree.

---

## D. Verification

```text
before 4a:  104 passed / 297 assertions
after  4a:  112 passed / 319 assertions   (+8 SubscriberTest)
```

Tests cover: permission gate, listing all detail columns (incl. PRAN + department via trim),
"pending" for drafts, search by name/account, the status filter, and the filtered Excel export.

---

## E. Still to come in M4

4b view one subscriber · 4c register (draft) · **4d finalize + allot the account number
(the counter — transaction + row lock, Service class)** · 4e edit / close.
