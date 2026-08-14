# M4 · Slice 4f — Close Account (+ the closure register table) — Explained for Beginners

> **What this slice gives you, in one sentence:**
> A screen to **close a finalized account** with a reason and closing details, recorded in a
> dedicated **closure register** — while the account row just flips to inactive.

---

## A. Concepts you need first

| Term | Plain-English meaning |
|------|----------------------|
| **1:1 relationship** | One row here matches at most one row there. An account is closed *once*, so a closure is 1:1 with an account. |
| **Register table** | A table that *is* the record of a thing happening — here, `account_closure` is the list of closures. |
| **Single source of truth** | Each fact is stored in exactly one place. Name/PRAN/department live on the account, so the closure table does **not** copy them. |
| **Guard (on an UPDATE)** | A `WHERE` clause that makes a change happen only under the right condition — here `WHERE isactive = true`, so you can't close twice. |
| **Transaction** | Several writes that all succeed together or all roll back. |

---

## B. The big decision: a new table, not more flags

The legacy stored closure right on the account (`isActive`, `closure_reason_id`, `closure_date`).
We now also capture **closing date** + **deduction month/year**, and we want a clean *register*
of real closures. So we made a dedicated table:

```
account_closure  (one row per closed account)
├─ account_no        PRIMARY KEY  → also the link to allotment_accnt_no.account_no
├─ closure_reason_id  → m_closure_reason.id   (FK)
├─ closing_date       (operator-entered)
├─ deduction_month    (1–12)
├─ deduction_year     (e.g. 2026)
├─ closed_by          (user id, audit)
└─ created_at
```

Three design choices, each deliberate:

1. **`account_no` is the primary key** → the database itself guarantees "closed only once".
2. **We do NOT copy name / PRAN / department** into this table. They already live on the
   account (and `pran_no`). Copying invites drift (edit the name later, the closure copy goes
   stale). We **join** for them instead — one source of truth.
3. **The account keeps only `isactive`.** The rich closure detail lives in `account_closure`;
   `allotment_accnt_no.isactive = false` is the single "is it closed?" flag every list already
   checks. (There were 454 inactive rows in the data but only **2** truly closed with a reason —
   another reason the register, not `isactive`, is the real list of closures. Those 2 were
   backfilled by the migration.)

---

## C. Closing — one transaction, with a guard

```php
$closed = DB::transaction(function () {
    // Guard: only a still-open account is closed — never twice.
    $updated = Subscriber::where('account_no', $this->accountNo)
        ->where('save_flag', 'F')
        ->where('isactive', true)          // ← the guard
        ->update(['isactive' => false]);

    if ($updated === 0) {
        return false;                      // already closed → nothing written
    }

    AccountClosure::create([...]);         // the register row
    return true;
});
```

Why the transaction: the two writes (flip the flag **and** record the closure) must both land or
neither. Why the guard: if the account was closed a moment ago, `$updated` is `0`, we return
early, and **no** closure row is written — no double-close, no duplicate-key crash.

---

## D. The form (matches the legacy flow)

A **cascade**: **Department → Account No → Name + PRAN appear → Reason + Closing Date +
Deduction Month/Year → Close.** Choosing a department loads that department's *active* accounts;
choosing an account fills Name + PRAN (read-only). Below the form is the **Closed accounts
register** — searchable, with **Excel** and **PDF** export — read straight from `account_closure`
(joined to the account for name).

---

## E. How to verify yourself

```bash
php artisan migrate                       # creates account_closure (+ backfills 2 rows)
php artisan test --filter=CloseAccountTest
```

**Result:** 6 tests. The important ones: it closes an active finalized account (writes the
register row + flips `isactive`), it **won't close an account that's already closed** (the
guard), selecting an account fills Name + PRAN, closing requires reason + date + month + year,
and the register lists closures. Full suite at this slice: **146 passing**.

---

## F. Gotchas worth remembering

- **A "closed once" rule belongs in the schema** — make `account_no` the primary key and the DB
  enforces it for free.
- **Don't copy facts between tables.** Link by id and join; copies drift.
- **Guard state changes at the WHERE clause**, not just in the UI — `WHERE isactive = true`
  stops a double close even under a race.
- **Two writes that belong together go in one transaction.**
- **`isactive = false` ≠ "properly closed".** The real register is `account_closure`; legacy left
  inactive rows with no reason, so we list the register, not the flag.

---

## ✅ 4f — Close Account: COMPLETE

A dedicated closure register, a guarded one-transaction close, and a searchable/exportable
history. **Next: 4g — Edit an account.**
