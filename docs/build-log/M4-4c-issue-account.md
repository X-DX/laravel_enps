# M4 · Slice 4c — Issue Account (register a new subscriber) — Explained for Beginners

> **What this slice gives you, in one sentence:**
> The entry form where an operator types a new employee's details and clicks **Save** —
> the record is stored as a **draft** (`save_flag = 'T'`), with the account number left for
> later (finalize, 4d).

4a *listed* subscribers, 4b *showed* one. 4c is the **first screen that writes data**. It's
a big form, so it teaches a lot: matching property names to columns, a live cascade, a few
reactive behaviours, mass-assignment, and a reusable searchable dropdown.

Read section **A (concepts)** first if any term is new.

---

## A. Concepts you need first (mini-glossary)

| Term | Plain-English meaning |
|------|----------------------|
| **Draft** | A saved-but-not-finalized record. Here `save_flag = 'T'`; it becomes `'F'` (and gets an account number) at finalize. |
| **Livewire lifecycle hook** | A method Livewire calls automatically when a property changes, named **`updated` + PropertyName**. Misname it and it silently never runs. |
| **Cascade (dependent dropdown)** | One dropdown's options depend on another's choice. Here: pick a Treasury → the DDO list narrows to that treasury's DDOs. |
| **`$fillable`** | A model's *whitelist* of columns that `create()`/`update()` are allowed to set. Any key not on it is silently dropped. |
| **Blade component** | A reusable chunk of view, used like an HTML tag: `<x-searchable-select … />`. |

---

## B. Match your property names to the DB columns

Every input needs a matching public property in the component. We named them **exactly like
the `allotment_accnt_no` columns** — even the ugly legacy ones (`nameofdept` for a code,
`doapptorder` for the appointment date). Two reasons:

1. **No mental translation** — when you look at the table you instantly know which property
   fills which column.
2. **Saving is almost a direct copy** — `['name' => $this->name, 'dob' => $this->dob, …]`.

The one exception is **`$treasury_code`**, which is *not* a column — it's a **form-only
helper** that filters the DDO dropdown and is then thrown away (the treasury is derived from
the chosen DDO; we store only `ddocode`).

### Not every column is a form field

The 29 columns of `allotment_accnt_no` fall into three buckets:

- **① The operator types it → a property** (name, parents, dates, designation, department,
  pension type, pay, ddocode, nominees, deduction start).
- **② The system sets it at save → in `save()`, not the form:** `save_flag = 'T'`,
  `entry_date = today`, `user_id = the logged-in user`, `flag_pt = 'N'`,
  `closure_reason_id = 0`, `isactive = true`. (These match the legacy insert exactly.)
- **③ Filled in later:** `id` (auto), `account_no` + `finalize_date` (at finalize, 4d),
  `closure_date` (only if closed, 4e).

---

## C. The three "smart" behaviours (Livewire hooks)

Livewire watches your properties and calls `updated{Property}()` when one changes:

- **`updatedTreasuryCode()`** → clears the chosen DDO (the DDO list is rebuilt in `render()`
  to show only that treasury's DDOs — the cascade).
- **`updatedSingleMotherFlag()`** → clears the Father Name.
- **`updatedDob()`** → auto-fills the Retirement Date (DOB + retirement age, end of month;
  the age comes from the `retirement_year` setting, usually 60).

> **The bug we hit (twice):** `updateDob()` (missing a "d") and `updatedSingleMother()`
> (wrong property; it's `single_mother_flag`) both compiled fine, threw no error, and simply
> **never ran** — so the behaviours were dead. A misnamed hook is invisible to `php -l`; only
> reading the name carefully (or a test) catches it. **The hook name must be `updated` +
> the exact StudlyCase of the property.**

The pay **label** is also reactive (in the view): NPS → "Basic + DA", UPS → "Basic Pay",
driven by `wire:model.live` on `pension_type`.

---

## D. Saving (and the mass-assignment gotcha)

`save()` validates, then `Subscriber::create([...])` with the operator fields **plus** the
six system fields, then `$this->reset()` to blank the form for the next entry, and a success
toast.

> **The second bug we hit:** the insert failed with *"null value in column
> `closure_reason_id` violates not-null"* — even though `save()` set it to `0`. The cause:
> `closure_reason_id` was **missing from the model's `$fillable`**, so Eloquent **silently
> dropped it** (that's mass-assignment protection). **Lesson: if a value "vanishes" on save,
> check `$fillable` first** — and a `NOT NULL` column turns that silent drop into a loud
> database error.

Validation notes: `name` is 8–50 chars; `father_name` is `required` *unless* Single Mother
is ticked (`$this->single_mother_flag ? 'nullable' : 'required'`); `exists:` rules confirm the
chosen designation / department / DDO are real rows.

---

## E. The searchable dropdowns (a reusable component)

A plain HTML `<select>` can't be searched — a browser limitation. The legacy used a library
(Select2). We built a small reusable **`<x-searchable-select>`** (Alpine + Livewire, no
external library): a button, a search box that filters as you type, and a list. It's
`@entangle`d to a Livewire property, so a selection updates the form.

Used for **Designation** (1,800+ rows — search is essential), **Department**, **Treasury
Location**, and **DDO**. The DDO one carries `wire:key="ddo-{{ $treasury_code }}"` so that
when the treasury changes, Livewire rebuilds it with the new list. Treasury and DDO show
**"name (code)"**.

*(We also filtered out one bad `designation_master` row with an empty name, which had been
showing as a blank first option.)*

---

## F. Wiring: route, permission, sidebar, button

```php
Route::get('/accounts/issue', IssueAccount::class)
    ->middleware('can:entrysection.issue_account')
    ->name('accounts.issue');
```

- Permission `entrysection.issue_account` is an existing legacy one (menu item 152).
- The **show** route gained `->whereNumber('subscriber')`, so `/accounts/issue` (a word)
  can't be mistaken for "show subscriber id `issue`".
- `SidebarMenu::ROUTES` maps the permission → route (the sidebar "Issue Account" lights up).
- The list page (4a) gained a **"+ New Account"** button linking here.

---

## G. How to verify 4c yourself

```bash
php artisan test --filter=IssueAccountTest
php artisan test
```

**Result:** 7 feature tests; the full suite went from **117 → 124 passing / 358 assertions**.
The tests cover: the permission gate, the DOB→retirement auto-fill, Single-Mother clearing
Father, the Treasury→DDO cascade (and clearing a stale DDO), the "father required unless
single mother" rule, and a full happy-path save that checks the **system fields**
(`save_flag = 'T'`, `account_no` null, `flag_pt = 'N'`, `closure_reason_id = 0`, etc.). The
test table makes `closure_reason_id` NOT NULL on purpose, so it guards against the fillable
regression ever coming back.

---

## H. Gotchas worth remembering

- **Name properties like the columns** for a legacy-mapped form — saving becomes a direct
  copy, and there's no translation to get wrong.
- **A misnamed Livewire hook silently does nothing.** `updated` + exact StudlyCase.
- **`$fillable` is a whitelist; `create()` drops anything not on it — silently.** A vanished
  value on save → check `$fillable`.
- **A form-only helper (`treasury_code`) is not a column** — mark it clearly and never save
  it.
- **Native `<select>` can't search** — a reusable searchable-select solves it everywhere.

---

## ✅ 4c — Issue Account: COMPLETE

Operators can register new subscribers as drafts, with a live Treasury→DDO cascade, auto-fill
retirement date, and searchable dropdowns. **Next: 4d — finalize + allot the account number
(the counter: a transaction + row lock, in a Service class).**
