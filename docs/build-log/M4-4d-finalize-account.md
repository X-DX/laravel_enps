# M4 · Slice 4d — Finalize + allot the account number — Explained for Beginners

> **What this slice gives you, in one sentence:**
> The operator ticks one or more **Pending** subscribers and clicks **Finalize** — each one
> gets its real **account number** (`AP/NPS/{dept}/{seq}`) and turns **Finalized** (`F`).

This is the **most important slice of M4**, because generating that number *safely* — so two
operators can never produce the same one — is the whole point. It's also where we introduce
the **Service class** pattern.

Read section **A (concepts)** first if any term is new.

---

## A. Concepts you need first (mini-glossary)

| Term | Plain-English meaning |
|------|----------------------|
| **Finalize** | Turn a draft (`save_flag = 'T'`) into a real account: give it an account number, set `save_flag = 'F'`, stamp `finalize_date`. |
| **Counter** | A stored running number, one per department (`account_sequence`). "Give me the next account number" = add 1 to this and use it. |
| **Race condition** | A bug where two things happen at the same time and step on each other — here, two finalizes reading the same counter value. |
| **Atomic operation** | A step the database does in "one shot" that can't be interrupted half-way. |
| **Row lock** | The database temporarily reserves a row so only one transaction can change it at a time; others wait. |
| **Transaction** | A group of database changes that all succeed together or all undo together. |
| **Service class** | A plain PHP class that holds a piece of *business logic*, kept out of the UI (the Livewire component). |
| **Dependency injection** | You *ask* for an object (by type-hinting it) and the framework *builds and hands* it to you. |

---

## B. The account number's shape

```
AP / NPS / 15 / 0042
│    │     │     └── the department's next running number (min 4 digits, zero-padded)
│    │     └──────── the department code (the subscriber's nameofdept)
│    └────────────── NPS or UPS  (from pension_type: N→NPS, U→UPS)
└─────────────────── the state
```

The running number comes from **`account_sequence`** — one counter row per department.

---

## C. The one hard problem: the counter race

To allot a number you must **read the counter → add 1 → use it → save it back.** The danger:

```
Operator A reads 41          Operator B reads 41
A builds AP/NPS/15/0042      B builds AP/NPS/15/0042   ← SAME NUMBER! 💥
```

Two subscribers with the **same account number** — a serious data bug. The fix is to make
"add 1 and tell me the new value" a **single atomic step that also locks the row**:

```php
// in App\Services\AccountFinalizer::finalize()
$affected = DB::update(
    'UPDATE account_sequence SET account_seq_no = account_seq_no + 1 WHERE trim(dept_code) = ?',
    [$deptCode]
);
$seq = DB::table('account_sequence')->whereRaw('trim(dept_code) = ?', [$deptCode])->value('account_seq_no');
```

Why this is safe:
- The `UPDATE … + 1` happens **atomically** (the database does the read-and-write itself) and
  **locks** the department's counter row until our transaction commits.
- So a second finalize on the **same department** must **wait** for the first to finish — then
  it reads `42`, adds 1, gets `43`. Never a duplicate.
- We read the value **back inside the same transaction**, so we see our own new number and the
  lock guarantees nobody changed it in between.

> **Why not Postgres's one-line `UPDATE … RETURNING`?** It's Postgres-only, and our tests run
> on SQLite. This two-step form is atomic-and-safe on **both**, and it's exactly what the
> legacy app does. (Same spirit as `LOWER() LIKE` vs the Postgres-only `ILIKE`.)

`finalizeMany()` wraps the whole batch in **one transaction** (`DB::transaction`): all selected
drafts finalize together, or — if anything fails — they **all** roll back, leaving no
half-finished batch. It also skips ids that aren't drafts anymore (someone else already
finalized them).

---

## D. The Service class (a new pattern)

That counter logic is **business logic**, and it does **not** belong inside a Livewire
component (which should only run the screen). So we put it in `App\Services\AccountFinalizer` —
a plain PHP class whose only job is "finalize a subscriber safely."

The Livewire component just calls it, and gets the service via **dependency injection**:

```php
public function finalize(AccountFinalizer $finalizer): void   // Livewire builds & hands us the service
{
    $this->authorize('entrysection.issue_account');
    // ...
    $done = $finalizer->finalizeMany($this->selected);
    $this->selected = [];
    $this->dispatch('notify', type: 'success', message: count($done).' subscriber(s) finalized.');
}
```

Why this is better: the component stays **thin** (permission + call + toast), the risky logic
is **testable on its own**, and it's **reusable** (a batch today, a scheduled job tomorrow —
all call the same service).

---

## E. The UI — batch finalize from the list

The **View All Accounts** list (4a) gained:
- a **checkbox** on each Pending row (`wire:model.live="selected"`), and a **header checkbox**
  that selects/deselects all the page's pending rows (`toggleSelectAll()`),
- a **"Finalize selected (N)"** button (`wire:confirm` before it runs, since it's irreversible),
- both gated by `@can('entrysection.issue_account')`, so only users allowed to issue accounts
  see them.

Selecting a single row and finalizing is just the batch with one id. Select-all works **per
page** on purpose — you can never accidentally finalize thousands with one click.

---

## F. How to verify 4d yourself

```bash
php artisan test --filter=FinalizeAccountTest
php artisan test
```

**Result:** 7 tests; the full suite went from **124 → 131 passing / 372 assertions**. The tests
prove: a finalize allots the right number and advances the counter; UPS gets the `UPS` prefix;
**two drafts in the same department get *consecutive* numbers with no collision**; already-
finalized rows are skipped (counter untouched); the list action finalizes the selected drafts
and clears the selection; an empty selection changes nothing; and select-all picks only the
pending rows.

---

## G. Gotchas worth remembering

- **A shared counter is a race waiting to happen.** Make "increment and read" one atomic,
  locked step, inside a transaction.
- **Write it cross-DB** — the atomic `UPDATE +1` then read works on Postgres *and* SQLite;
  Postgres-only `RETURNING` would break the tests.
- **Business logic → a Service class, not the component.** Thin UI, testable logic, reusable.
- **Batch = one transaction** — all-or-nothing, so a failure never leaves a half-finished run.
- **Irreversible action → confirm first** (`wire:confirm`), and gate it behind the right
  permission.

---

## ✅ 4d — Finalize + allot account number: COMPLETE

Drafts become real accounts with safe, collision-free numbers, via the project's first Service
class. **Next: 4e — edit / close a subscriber** (the last piece of M4).
