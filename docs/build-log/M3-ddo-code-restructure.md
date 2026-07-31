# M3 change — DDO Master: a real DDO Code (serial → `ddo_sl` + new `ddo_code`)

> **What this is, in one sentence:**
> The old `ddo_code` was just an auto-increment serial, so we renamed it to `ddo_sl` and
> added a *real* 7-digit `ddo_code` that admins type — with a rule that the same code can't
> repeat inside one treasury.

This slice was built **hand-typed, one concept at a time** (the new way of working). It's a
great case study in changing a table that already has **3,085 live rows** without breaking or
losing anything.

---

## A. Mini-glossary

| Word | Plain meaning |
|---|---|
| **Serial** | An auto-increment number the database hands out (1, 2, 3…). Good as an internal *identity*, meaningless as a *business* value. |
| **Composite unique** | A uniqueness rule on **two columns together** — here `(treasury_code, ddo_code)`. |
| **Transactional DDL** | The database can wrap schema changes in a transaction, so a failed migration rolls back *entirely*. Postgres does this; MySQL does not. |
| **Progressive backfill** | Old rows start blank and get the new value as each is edited. |

---

## B. Why the change

The column called `ddo_code` was actually a `bigint` auto-increment **serial** — an internal
row number, not the DDO's real code. We wanted an actual **7-digit DDO code** (like `0012345`)
that people recognise, *and* to keep the serial as the primary key.

So:
- **`ddo_code` → `ddo_sl`** — the serial keeps being the primary key, just honestly named.
- **new `ddo_code varchar(7)`** — the real, hand-typed business code.
- **rule:** a DDO code can't appear twice **in the same treasury** (but the same code is fine
  in a *different* treasury).

---

## C. Concept 1 — the migration (and two lessons it taught)

```php
// up()
Schema::table('ddo_master', fn (Blueprint $t) => $t->renameColumn('ddo_code', 'ddo_sl'));
Schema::table('ddo_master', function (Blueprint $t) {
    $t->string('ddo_code', 7)->nullable();
    $t->unique(['treasury_code', 'ddo_code']);
});
```

**Was the rename safe?** We checked first (never assume):
- `ddo_code` was a `bigint` PK with a sequence → confirmed it's a serial.
- **No other table** had a `ddo_code` column, and **no foreign key** referenced `ddo_master`.
- So renaming broke *nothing*. The PK and the auto-increment sequence followed the column
  automatically.

**Lesson 1 — the failure that rolled back cleanly.** A typo (`treasury_id` instead of
`treasury_code`) made the migration fail *after* the rename had run. On Postgres, the whole
migration is wrapped in a transaction, so the failure **undid everything** — the table was
untouched, and we just fixed the word and re-ran. On MySQL this would have left a half-migrated
mess to repair by hand. A real reason Postgres is nice.

**Lesson 2 — nullable + composite unique co-exist with legacy rows.** `ddo_code` is nullable
(the 3,085 existing DDOs have none yet). The unique index is on `(treasury_code, ddo_code)`,
and Postgres treats NULLs as *distinct* — so 3,085 `(NULL, NULL)` rows don't trip the rule.

---

## D. Concept 2 — the model (one line)

The DB column moved, so Eloquent had to be told:

```php
protected $primaryKey = 'ddo_sl';   // was 'ddo_code'
```

`$incrementing`/`$keyType` stayed (still an auto-increment bigint), and `fillable` already
listed `ddo_code` — which now points at the new varchar, exactly the field we mass-assign.

---

## E. Concept 3 — the component (the duplicate rule)

```php
'ddo_code' => [
    'required', 'string', 'regex:/^[0-9]{7}$/',
    Rule::unique('ddo_master', 'ddo_code')
        ->where('treasury_code', $this->treasury_code)   // ← scope: within THIS treasury
        ->ignore($this->editingCode, 'ddo_sl'),          // ← don't count the row against itself
],
```

- `regex:/^[0-9]{7}$/` = *exactly seven digits* — pins length **and** digits-only in one rule.
- `->where('treasury_code', …)` is what makes it **per-treasury**: `0012345` may exist in
  treasury `01` and treasury `02`, just not twice in `01`.
- `->ignore(…, 'ddo_sl')` uses the **new** key name, so editing a DDO doesn't flag itself.

The bug we hit here (twice, actually): the two "codes" are different things now. `editingCode`
must read `$ddo->ddo_sl` (identity); the form field reads `$ddo->ddo_code` (data). Mixing them
up made `edit()` silently misbehave — caught by *reading the code*, not by `php -l`.

---

## F. Concepts 4–5 — view & export

- **View:** new **DDO Code** input (`type="text"` so `0012345` keeps its leading zero); the
  table shows `DDO Sl · DDO Code · DDO Name · Treasury · District`; every button keys off
  `ddo_sl`, the row identity.
- **Export:** added the `DDO Sl` column; `headings()` and `map()` kept the same order and count.

---

## G. Concept 6 — the tests (the proof)

```text
DdoTest:      16 passed (44 assertions)
full suite:  104 passed (297 assertions)   (was 101 / 287)
```

The three new tests encode the requirement exactly: duplicate-in-same-treasury → **blocked**;
same-code-in-different-treasury → **allowed**; not-7-digits → **blocked**.

---

## H. Commands we ran

```bash
php artisan make:migration rename_ddo_code_to_ddo_sl_add_ddo_code_on_ddo_master --table=ddo_master
php artisan migrate                 # (failed once on a typo, rolled back, fixed, re-ran)
php artisan db:table ddo_master     # eyeball the new columns
php -l app/...                      # syntax check after each edit
php artisan test --filter=DdoTest   # focused
php artisan test                    # whole suite
```

---

## I. Gotchas worth remembering

- **A serial is not a business code.** If people quote it, it deserves its own column.
- **Check before renaming a key** — no FK, no other table using the name = safe.
- **`php -l` ≠ working.** It proves the file parses. Missing properties and wrong column names
  sail right past it; reading and tests catch them.
- **Postgres rolls back failed migrations**; lean on it, but still write a correct `down()`.
- **Composite unique + NULLs**: Postgres treats NULLs as distinct, so nullable code columns
  don't break the rule for un-backfilled rows.

---

## ✅ Done. DDOs now carry a real, per-treasury-unique 7-digit code — built by hand, one concept at a time.
