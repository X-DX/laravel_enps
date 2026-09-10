# M6 · Central Register — the three numbers, and the counter race

> **Read this if you were ever confused by `central_reg`.** Most people are. The table stores
> **three different identifiers** with near-identical names, and the old UI made it worse.

---

## 1. The glossary (learn this first)

Two tables are involved.

| Table | One row = |
|---|---|
| `first_receipt` | one draft/cheque as it physically arrived from a DDO |
| `central_reg` | that draft's official booking into the Central Register |

And **three** numbers, which must never be conflated:

| Number | Column | Unique per | Plain English |
|---|---|---|---|
| **First Receipt No** | `first_receipt.sl_no` | draft | The row id of the draft when it was typed in. Auto-increment. Not a receipt anyone receives. |
| **CR No** | `central_reg.sl_no` | draft | Its serial number in the Central Register ledger. |
| **Receipt No** | `central_reg.receipt_no` | **batch** | The number printed on the paper acknowledgement handed back to the DDO. **Shared by every draft finalized together.** |

The fourth column, `central_reg.first_receipt_sl_no`, is just the foreign key linking the booking
back to the draft.

### Why one Receipt No covers many drafts

A DDO sends 471 drafts in one bundle. The office does not hand back 471 slips — it hands back
**one** acknowledgement. So:

```
receipt_no 33774  →  471 rows in central_reg
                     CR Nos 240361 … 240831   (471 distinct serials)
```

Live proof from our data: **358,646** register rows carry only **38,122** distinct receipt numbers.

---

## 2. How the money gets here (the 4 stages)

`first_receipt.flag` is the lifecycle.

| Flag | Meaning | Rows |
|---|---|---|
| `T` | Just entered at First Register | 342 |
| `CR` | Verified, **waiting** for a CR number | 1,663 |
| `FZ` | CR generated — the booking exists | 239,964 |
| `E` | Exported onward | 32,129 |

> ⚠️ **Correction to earlier notes.** An older comment in `FirstReceipt` (and in
> [M6-first-register.md](M6-first-register.md)) said *"CR = finalized, legacy also has FZ"*.
> That is backwards. `CR` means **still pending**, `FZ` means **done**. The row counts settle it,
> and `FirstReceipt::statusLabel()` is now the single source of truth for the wording.

The stage that matters here is **CR → FZ**: Entry CR (legacy menu 201).
The operator ticks drafts, presses *Generate CR No*, and each ticked draft gets a `central_reg` row.

---

## 3. The bug in the legacy code

Legacy [`CentralRegister.php:218`](../../../legacy-enps-CI/application/controllers/EntrySection/CentralRegister.php)
allocated numbers in **three separate steps**:

```
1. READ    counter_centralreg   → 240361
2. INSERT  central_reg          → sl_no = 240361
3. WRITE   counter back         → 240362
```

Between step 1 and step 3 there is a **gap in time**. Another operator can read in that gap.

### A transaction does NOT fix this

The legacy code *does* wrap this in `trans_start()`. It still produced **146 duplicate CR Numbers**.

> **The lesson worth remembering:** a transaction gives *atomicity* (all-or-nothing).
> It does **not** give *exclusivity*. A plain `SELECT` inside a transaction is a **non-locking
> snapshot read** — it does not stop anyone else reading the same row.

### Seeing it happen

Reproduced against a throwaway Postgres database, two sessions 0.5s apart:

```
OperatorA read CR No = 240361
OperatorB read CR No = 240361      ← both read the same value

 sl_no  |    who
--------+-----------
 240361 | OperatorA
 240361 | OperatorB      ← same CR No booked twice

 counter_now = 240362              ← advanced by 1, not 2
```

This is the classic **lost update**: A's increment was overwritten by B.

### The fix — make the read itself a lock

```sql
SELECT centregno FROM counter_centralreg WHERE cunterid = 1 FOR UPDATE
```

In Laravel that is `->lockForUpdate()`. Same test, only that change:

```
OperatorA read CR No = 240361
OperatorB read CR No = 240362      ← B waited, then re-read the FRESH value
```

The subtle part: when B unblocks it does **not** get the stale 240361 it was about to read.
`FOR UPDATE` re-reads the newest committed row. B was made to **wait** — that is the whole point.

### Why not a Postgres sequence?

`nextval()` is faster and lock-free, and it is the right answer for an ordinary `id`. But sequences
are **non-transactional by design** — if a transaction rolls back, the number is consumed anyway:

```
A takes 240361 → transaction fails → rolled back
B takes 240362 → commits
                 ⇒ CR No 240361 does not exist
```

A hole in a **statutory register** is what an auditor points at and asks *"who deleted this
entry?"* Gapless matters more than speed here, so we keep the counter row and lock it.

**This is a business constraint overriding the technically-faster option.** Worth noticing — it is
the kind of judgement call that does not appear in Laravel tutorials.

---

## 4. What we changed

### 4a. Database guarantee — [`2026_09_10_000001_harden_central_reg_integrity.php`](../../database/migrations/2026_09_10_000001_harden_central_reg_integrity.php)

`lockForUpdate()` is *application* logic, and application logic can be bypassed by a script, a
future developer, or a bug. So the rule now also lives in the database.

| Change | Why |
|---|---|
| **Renumbered 146 duplicate CR Nos** | Prerequisite for the key. **Business decision: renumber, never delete** — every register row is preserved and the later of each colliding pair gets a fresh number from the top of the counter. |
| **New `central_reg_renumber_log`** | Permanent old→new audit trail. An auditor asking *"why did CR 3110 become 362068?"* must have an answer. |
| **`PRIMARY KEY (sl_no)`** | A duplicate CR Number is now **impossible**, whatever code runs. |
| **Index on `first_receipt_sl_no`** | Every CR screen joins on it. The table had **zero** indexes on 358k rows. |
| **Index on `receipt_no`** | Used by the lists and by the "attach to an existing receipt" lookup. |
| **FK → `first_receipt (sl_no)`, `NOT VALID`** | Enforces the link for every **new** row without re-checking history (103,281 pre-migration rows have no link, 18 point at deleted drafts — real history we were told not to touch). Validate later once triaged. |

Result: 148 rows renumbered (146 groups, some with three rows), **358,646 rows in, 358,646 out**.

`down()` reverses the whole thing using the log — rehearsed and verified.

### 4b. `generate()` — [EntryCr.php](../../app/Livewire/CentralRegister/EntryCr.php)

`lockForUpdate()` was already there and correct. What was wrong:

- **An N+1 inside the lock.** `$fr->bank` fired one query per draft — 471 round trips on a big
  batch, *all while every other operator in the office was blocked*. Now eager-loaded outside.
- **Attach validation ran inside the transaction**, holding the counter during a read-only check.
  Moved out; the method now returns before opening a transaction it does not need.
- **471 individual INSERTs.** Now batched (chunks of 500).

> **The rule:** the lock is held until `COMMIT`. Do the minimum inside it. Never generate a PDF
> while holding the counter.

### 4c. The relation was non-deterministic — [FirstReceipt.php](../../app/Models/FirstReceipt.php)

249 legacy drafts were booked into the register **twice** — different CR Nos, different Receipt
Nos, sometimes different operators:

```
first_receipt 9168  →  CR 97466 / Receipt 25728
                    →  CR 98404 / Receipt 25889
```

`hasOne` with no ordering lets Postgres return **either** — so the CR No on screen could change
between two refreshes of the same page. Now:

- `centralReg()` is ordered by `sl_no` → always the earliest (original) booking.
- `centralRegs()` (new, `hasMany`) exposes all of them.
- `primaryCentralReg()` / `isDoubleBooked()` read the eager-loaded collection, so the lists never
  fire a second query per row.

**Business decision: show both, flag them.** Nothing is hidden or deleted; the row carries an
amber `×2` badge naming every CR No.

### 4d. The screens — View All / Finalized CR Entries

The headings were actively misleading. Fixed:

| Old heading | Showed | New heading |
|---|---|---|
| Receipt No | `first_receipt.sl_no` | **First Receipt No** |
| CR Receipt No | `central_reg.receipt_no` | **Receipt No** |
| *(missing entirely)* | `central_reg.sl_no` | **CR No** |

`CR Receipt No` was our own invention — that term exists nowhere in the business. **CR No** was
never displayed at all, despite being the number staff quote to each other.

Also added:

- **Blocked badge** — 626 register rows are blocked (under objection) and were completely
  invisible. An operator must never read this screen and assume a booking is clear.
- **Entry-date range filter**, blank by default. Legacy silently defaulted to *today* and hid
  everything else; we browse the whole register and let the operator narrow it.
- **Search that can use an index.** Was `CAST(sl_no AS TEXT) LIKE '%…%'` — a full scan of 274k
  rows on every keystroke. A digits-only term is now matched as a **number** against all three
  identifiers; anything else searches draft/order number.

  ```
  WHERE receipt_no = 33774                      Index Scan    1.0 ms
  WHERE CAST(receipt_no AS TEXT) LIKE '%33774%' Seq Scan     85.6 ms   ← 82× slower
  ```

- Excel and PDF match the screen exactly, including Blocked, Blocked Reason and Duplicate CR
  Entries columns, plus a legend explaining the three numbers.

---

## 5. Commands used

```bash
# Rehearse the migration on a throwaway copy — never on real data first
createdb -h 127.0.0.1 -U aruproy enps_migration_rehearsal
pg_dump -h 127.0.0.1 -U aruproy -d enps --no-owner --no-privileges \
  | psql -h 127.0.0.1 -U aruproy -d enps_migration_rehearsal
DB_DATABASE=enps_migration_rehearsal php artisan migrate --force
DB_DATABASE=enps_migration_rehearsal php artisan migrate:rollback --step=1 --force   # prove down()
dropdb -h 127.0.0.1 -U aruproy enps_migration_rehearsal

# Then for real
php artisan migrate --force

# Tests
php artisan test tests/Feature/CentralRegister/
php artisan test
```

Verify afterwards:

```sql
SET search_path TO enps;
SELECT count(*) FROM (SELECT sl_no FROM central_reg GROUP BY sl_no HAVING count(*)>1) t;  -- 0
SELECT count(*) FROM central_reg_renumber_log;                                            -- 148
SELECT indexname FROM pg_indexes WHERE tablename='central_reg';                            -- 3
```

---

## 6. Verification

**219 tests passing**, up from 208 — 11 new (8 on the CR lists, 3 on CR generation).

The two that matter most:

- `test_the_database_itself_rejects_a_duplicate_cr_number` — proves the safety net, not just the
  application logic.
- `test_generate_does_not_issue_a_query_per_draft_while_holding_the_lock` — asserts the query
  count for 3 drafts equals the count for 30. **Confirmed to catch the regression**: reinstating
  the N+1 made it fail with `24 → 51`.

---

## 7. Gotchas

- **`ctid`** was the only way to address individual duplicate rows during the renumber, because
  `central_reg` had no primary key yet. Safe here: each ctid is captured first and updated once,
  and updating one row does not move the others.
- **`NOT VALID`** is a Postgres-only escape hatch — it enforces a constraint going forward without
  re-checking history. Ideal for legacy data you are not ready to clean.
- **The migration is Postgres-only** and returns early on other drivers, because the test suite
  builds its schema by hand on SQLite.
- **`assertDontSee('CR Receipt No')`** is deliberately in the test suite. It stops the wrong term
  creeping back in.

---

## 8. Legacy data — decisions taken (2026-09-10)

All three were investigated and **deliberately left in place**. None of them can affect the new
application, and each was verified rather than assumed.

### 8a. 103,281 register lines with no cheque linked — LEAVE

**Not a broken migration.** `central_reg` starts in 2009; `first_receipt` (the cheque list) only
starts in **2017**. Before ~2018 the office wrote straight into the register, and the
`first_receipt_sl_no` pointer column did not exist yet. The changeover is visible in the data:

| Year | No link | Linked |
|---|---|---|
| 2009–2017 | 39,581 | 10 |
| 2018 | 12,130 | 5,463 |
| 2019 | 705 | 28,248 |
| 2020 → | 0 | all |

A partial repair was attempted years ago —
[`UpdateCenModel::updateM()`](../../../legacy-enps-CI/application/models/UpdateCenModel.php) —
but it only covered `date_of_entry BETWEEN '2005-01-01' AND '2009-04-30'`, which is why just 9
rows in 2009 are linked. Matching the rest on draft number + amount recovers only 15,249;
**87,541 have no matching cheque at all** because the row was never created.

### 8b. 18 lines pointing at a deleted cheque — LEAVE

Initially recorded here as "18 blank rows, safe to delete". **That was wrong** — it read the
`amount` column on the register line (which is 0) without checking what pointed *at* the line.
Eleven tables reference `central_reg`. A proper check found the 18 are three different things:

| Group | Rows | Reality |
|---|---|---|
| Has money | 4 | 5 **exported** contribution rows in `employee_reg_oldcr` (₹7,792 · ₹3,512 · ₹14,009 + ₹14,871 · ₹6,723) |
| Sole line of its receipt | 10 | Deleting erases 7 whole Receipt Numbers; 5 were **printed and posted to a DDO** |
| Genuinely inert | 4 | CR 128908, 130185, 362057, 362059 |

Deleting the first group would leave real subscriber contributions pointing at nothing; the second
would erase receipts a DDO physically holds. Neither is worth trading for a tidy table.

> **The lesson:** before deleting from a legacy table, find every column in the schema that
> references it (`information_schema.columns` on `%cr_no%`, `%cr_sl%`) and count the dependants.
> A row that looks empty is not the same as a row nothing depends on.

### 8c. 249 double-booked drafts — LEAVE, pending a business decision

Same cheque booked into the register twice. Characterised: **239 of 249** got two *different*
Receipt Nos, **240 of 249** were the *same* operator, amounts identical, gaps of 0–77 days. So it
is one clerk repeating a batch weeks later, **not** the counter race. 254 extra bookings totalling
**₹31,21,208**. Exported for review; the owner is discussing it internally.

Open question nobody has answered yet: the register says the money arrived twice, but whether the
**contribution ledger** also credited it twice has not been traced. That decides whether this is a
paperwork problem or a money problem.

### Why leaving all three is safe for the new app — verified, not assumed

- The three CR list screens all query **`FROM first_receipt`**, so a register line with no cheque
  can never appear on them. Searching for orphan CR 362057 returns nothing on all three modes.
- `CentralReg::firstReceipt()` exists but is **called nowhere**, so no code path can dereference a
  null cheque.
- The only direct `central_reg` reads are the "attach to an existing receipt" lookup and its
  validation ([EntryCr.php](../../app/Livewire/CentralRegister/EntryCr.php)); both filter by
  `receipt_no` + `user_id` and never touch the cheque. Tested against an orphan receipt: correct
  answer, no error.
- The foreign key was deliberately added **`NOT VALID`**, so these rows are exempt while every new
  row is checked. Do not run `VALIDATE CONSTRAINT` — it would fail on exactly this history.

**One thing to remember for M7 (Contributions):** 5 contribution rows in `employee_reg_oldcr` point
at register lines whose cheque is missing. Any future report joining contribution → register →
cheque must tolerate a missing cheque. That is a blank field, not a crash — but design for it.
