# M3 change — DDO Master: Location → Treasury (explained for beginners)

> **What this is, in one sentence:**
> We re-pointed the DDO Master screen from **Location** to **Treasury** — a DDO now belongs
> to a *treasury* (chosen via District → Treasury), while the old location data is kept but
> hidden.

This wasn't a new screen — it was **changing an existing module** that already has **3,085
live rows**. That makes it a good lesson in doing a change *without breaking or losing data*.

---

## A. Mini-glossary

| Word | Plain meaning |
|---|---|
| **Additive change** | We *add* a column; we don't rename or delete the old one. Nothing existing breaks. |
| **Nullable + FK** | A column that may be empty, but if it holds a value that value must be real. (See §C — this is the exact question we dug into.) |
| **Progressive backfill** | Old rows start empty and get the new value as a human edits each one, instead of one risky mass update. |
| **Cascading dropdown** | Pick District → the Treasury list narrows to that district → then pick the treasury. |

---

## B. Legacy vs. new

**Before:** a DDO belonged to a **Location** (`ddo_master.loc_code`). The form was District →
Location → name; the table showed Location + District; search was District → Location.

**After (what you asked for):** a DDO belongs to a **Treasury**. Everywhere Location appeared,
Treasury takes its place:

| Area | Before | After |
|---|---|---|
| Add form | District → **Location** → name | District → **Treasury** → name |
| Table | Code · Name · **Location** · District | Code · Name · **Treasury** · District |
| Search | District → **Location** | District → **Treasury** |
| District comes from | ddo → location → district | ddo → **treasury** → district |

---

## C. The data-model decision (and the nullable-FK question)

We **added** `ddo_master.treasury_code` and **kept `loc_code` exactly as it was**:

```php
$table->string('treasury_code', 10)->nullable()->after('loc_code');   // ① may be empty
$table->foreign('treasury_code')                                      // ② if set, must be real
    ->references('treasury_code')->on('treasury_master')
    ->nullOnDelete();
```

Why keep `loc_code`? **Phase A rule: never destroy legacy data.** The 3,085 DDO→Location links
still live in the column; we just stopped *showing* them. If we ever need them (old reports,
Phase B), they're right there.

**How can a column be BOTH a foreign key AND nullable?** Because the two rules answer
different questions:

| Value stored | Nullable rule | FK rule | Result |
|---|---|---|---|
| `NULL` (the 3,085 old DDOs) | ✅ allowed | ⏭️ **skipped** — a FK never checks NULL | ✅ stored |
| `'01'` (real treasury) | ✅ allowed | ✅ found | ✅ stored |
| `'99'` (no such treasury) | ✅ allowed | ❌ not found | ❌ rejected |

A foreign key only checks **non-NULL** values. So "empty" is allowed, but any real value must
point at a real treasury. We *proved* this live: adding the FK to a table with 3,085 NULL rows
succeeded without complaint.

---

## D. The screen logic (same shape as before, Treasury swapped in)

- **Model** `Ddo` gained `treasury()` (belongsTo) and a `scopeForTreasuryFilter($dist, $tsy)`.
  We **kept** `location()` and its scope too — the old data still has a valid relationship.
  Note `treasury_code` is varchar on *both* tables, so — unlike `loc_code` — there's **no
  varchar/bigint casting dance**. Cleaner.
- **Two cascades** (unchanged pattern): the **list filter** (District → Treasury) and the
  **form** (District → Treasury → name). Changing the district clears the stale treasury in
  both.
- **Editing an old DDO:** its `treasury_code` is NULL, so the form opens with empty District +
  Treasury (the "— not set —" state). Because treasury is `required`, the admin must pick one
  to save — that's the progressive backfill in action. On save we update only the name +
  treasury; **`loc_code` is left untouched.**
- **New DDOs** get a treasury and no location (`loc_code` stays NULL — which is allowed).

---

## E. What to expect right now (be aware)

- **All 3,085 existing DDOs show "— not set —" for Treasury.** There is no safe automatic
  backfill: a district has *many* treasuries, so nothing can guess which one an old DDO
  belongs to. They fill in as they're edited — or via a bulk rule you define later.
- The **District → Treasury dropdown is only as full as Treasury Master.** Add treasuries
  there first, or the form has nothing to pick.

---

## F. Commands we ran

```bash
php artisan migrate     # adds treasury_code + the nullable FK
php artisan test        # the safety net
```

Live verification: `treasury_code` = `character varying(10)`, nullable; 3,085 DDOs, 0 with a
treasury yet; 1 foreign-key constraint on `ddo_master`.

---

## G. Verification

```text
before this change:  100 passed / 283 assertions
after  this change:  101 passed / 287 assertions
```

The DDO tests were rewritten to use treasuries, plus a new one proving a legacy DDO (loc set,
treasury NULL) keeps its `loc_code` and gains the chosen treasury on edit.

---

## H. Gotchas worth remembering

- **Change by adding, not renaming.** New `treasury_code`, old `loc_code` preserved → zero
  data loss, old app unaffected.
- **Nullable + FK = "optional, but valid if present."** The FK skips NULLs, which is exactly
  why 3,085 empty rows didn't block the constraint.
- **Matching varchar types** on both sides of `treasury_code` means no casting (contrast the
  legacy `loc_code` varchar-vs-bigint mismatch).
- **No auto-backfill for one-to-many parents.** District→many treasuries ⇒ only a human (or an
  explicit rule) can assign the right treasury to each old DDO.

---

## ✅ Done. DDO Master now runs on Treasury; the 3,085 legacy DDOs backfill one edit at a time.
