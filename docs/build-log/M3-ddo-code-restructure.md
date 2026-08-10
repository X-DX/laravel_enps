# M3 change — DDO Master: a real DDO Code — Explained for Beginners

> **What this change gives you, in one sentence:**
> The old `ddo_code` was really just an internal serial number, so we **renamed it to
> `ddo_sl`** and added a *real* hand-typed **7-digit `ddo_code`** — with a rule that the
> same code can't repeat inside one treasury.

This was our **first change to a legacy table that already had 3,085 live rows**, so it's
a good lesson in changing a table safely, without breaking or losing anything. It was
also the first slice built the "new way" — the developer types every line and runs every
command; the tests and git are done separately.

Read section **A (concepts)** first if any term is new.

---

## A. Concepts you need first (mini-glossary)

| Term | Plain-English meaning |
|------|----------------------|
| **Serial** | An auto-increment number the database hands out (1, 2, 3…). It's a good internal *identity*, but a meaningless *business* value. |
| **Primary key (PK)** | The column that uniquely identifies a row. Here it was `ddo_code`; now it's `ddo_sl`. |
| **Migration** | A PHP file that changes the database *structure* (rename a column, add one, add a rule). Run with `php artisan migrate`; undo with `migrate:rollback`. |
| **Composite unique index** | A "no duplicates" rule spanning **two columns together** — here `(treasury_code, ddo_code)`. |
| **Transactional DDL** | The database can wrap a structure change in a transaction, so if it fails midway, *everything* is undone. PostgreSQL does this; MySQL does not. |

---

## B. Why we made the change

The column named `ddo_code` was actually a `bigint` **auto-increment serial** — an
internal row number, not a DDO's real code. We wanted an actual **7-digit DDO code**
(like `0012345`) that people recognise, **and** to keep the serial as the primary key.
So:

- **`ddo_code` → `ddo_sl`** — the serial keeps being the primary key, just honestly named.
- **new `ddo_code varchar(7)`** — the real, hand-typed business code.
- **rule:** a DDO code can't appear twice **in the same treasury** (but the same code is
  fine in a *different* treasury).

### Why "numbers only" but stored as text

The codes look like `0012345` — always 7 **digits**, and the leading zero matters. If we
stored them as an integer, `0012345` would collapse to `12345` and the leading zero would
be gone. **Rule of thumb: if a value has a leading zero, or you never do arithmetic on
it, it's a *code* (store as text/`varchar`), not a *number*.** Same reason postal codes
and phone numbers are strings. We still enforce *digits only* in the form with a regex, so
"numbers only" holds — it's just the *storage type* that must be text.

---

## C. The migration (and two lessons it taught)

```php
// up()
Schema::table('ddo_master', fn (Blueprint $t) => $t->renameColumn('ddo_code', 'ddo_sl'));
Schema::table('ddo_master', function (Blueprint $t) {
    $t->string('ddo_code', 7)->nullable();
    $t->unique(['treasury_code', 'ddo_code']);
});
```

Note it's **two separate `Schema::table` blocks**: we rename *first*, so the column is
fully `ddo_sl` before we add a brand-new `ddo_code`. Doing a rename and a same-named add
in one breath is asking for trouble.

**Was the rename safe? We checked first, never assumed:**
- `ddo_code` was a `bigint` PK with a sequence → confirmed it was a serial.
- **No other table** had a `ddo_code` column, and **no foreign key** pointed at
  `ddo_master`.
- So renaming broke *nothing*. In Postgres, the primary-key constraint and the
  auto-increment sequence follow the column automatically.

### Lesson 1 — a failed migration that rolled back cleanly

While typing, a `treasury_id` typo (the column is `treasury_code`) made the migration
fail **after** the rename had already run. On **PostgreSQL**, Laravel wraps the whole
migration in a transaction, so the failure **undid everything** — the table was untouched,
and we just fixed the word and re-ran. On MySQL this would have left a half-migrated mess
to repair by hand. It's a real reason Postgres is nice — but still always write a correct
`down()`.

### Lesson 2 — nullable + a unique rule co-exist with legacy rows

`ddo_code` is **nullable** (the 3,085 existing DDOs have no code yet). The unique index is
on `(treasury_code, ddo_code)`, and Postgres treats `NULL`s as *distinct* — so 3,085
`(NULL, NULL)` rows don't trip the rule. New DDOs fill in a code; old ones stay blank
until edited.

---

## D. Teaching Eloquent about the rename (the model)

Renaming the column means telling the model its key moved:

```php
protected $primaryKey = 'ddo_sl';   // was 'ddo_code'
```

`$incrementing`/`$keyType` stay (it's still an auto-increment bigint), and `fillable`
already listed `ddo_code` — which now points at the new varchar, exactly the field we
mass-assign. If we *hadn't* changed `$primaryKey`, every `findOrFail`, `save`, and
`whereKey` would look for a `ddo_code` primary key that no longer exists.

---

## E. The duplicate rule (the component)

```php
'ddo_code' => [
    'required', 'string', 'regex:/^[0-9]{7}$/',
    Rule::unique('ddo_master', 'ddo_code')
        ->where('treasury_code', $this->treasury_code)   // scope: within THIS treasury
        ->ignore($this->editingCode, 'ddo_sl'),          // don't count the row against itself
],
```

- `regex:/^[0-9]{7}$/` reads as: *start*, *exactly seven digits*, *end*. That pins both
  the length and digits-only in one rule (so no separate `size:7` needed).
- `->where('treasury_code', …)` is what makes it **per-treasury**: `0012345` may exist in
  treasury `01` *and* in treasury `02`, just not twice in `01`. This mirrors the database
  index exactly.
- `->ignore(…, 'ddo_sl')` uses the **new** key name, so editing a DDO doesn't flag it as a
  duplicate of itself.

> **Belt and braces:** the app-level rule gives a *friendly message*; the database's
> unique index is the *hard guarantee* in case anything ever bypasses the form. Two
> layers, on purpose.

The subtle bug we hit (twice) lived here: after the rename there are **two "codes"**.
`editingCode` must read `$ddo->ddo_sl` (the row's identity); the form field reads
`$ddo->ddo_code` (the business code). Mixing them made `edit()` misbehave — and only
*reading the code* (or a test) caught it, never `php -l`.

---

## F. View & export

- **View:** a new **DDO Code** input (`type="text"` so `0012345` keeps its leading zero);
  the table shows `DDO Sl · DDO Code · DDO Name · Treasury · District`; every button keys
  off `ddo_sl` (the row identity), while `ddo_code` appears only where it's *displayed*.
- **Export:** added a `DDO Sl` column; `headings()` and `map()` kept the same order and
  count.

---

## G. How to verify this change yourself

```bash
php artisan test --filter=DdoTest
php artisan test
```

**Result:** the full suite went from **100 → 104 passing / 297 assertions**. Three of the
new tests exist purely to lock this rule down: *duplicate-in-same-treasury → blocked*,
*same-code-in-different-treasury → allowed*, and *not-7-digits → blocked*.

---

## H. Gotchas worth remembering

- **A serial is not a business code.** If people quote it, it deserves its own column.
- **Check before renaming a key** — no FK, no other table using the name = safe.
- **`php -l` proves it *parses*, not that it *works*.** Missing properties and wrong column
  names sail right past it; reading and tests catch them.
- **Postgres rolls back a failed migration** — lean on it, but still write a correct
  `down()`.
- **Composite unique + NULLs**: Postgres treats NULLs as distinct, so a nullable code
  column doesn't break the rule for un-backfilled rows.

---

## ✅ DDO real-code restructure: COMPLETE

DDOs now carry a real, per-treasury-unique 7-digit code, on top of a cleanly-named serial
primary key — built by hand, one concept at a time.
