# M3 change — DDO Master: Location → Treasury — Explained for Beginners

> **What this change gives you, in one sentence:**
> A DDO now belongs to a **Treasury** instead of a **Location** — the DDO screen (form,
> table, and search) all use Treasury now, while the old location data is *kept but
> hidden*.

This wasn't a new screen — it **changed an existing module that already had 3,085 live
rows**. That makes it a good lesson in changing something *without breaking or losing
data*, and it introduces the very useful idea of a **nullable foreign key**.

Read section **A (concepts)** first if any term is new.

---

## A. Concepts you need first (mini-glossary)

| Term | Plain-English meaning |
|------|----------------------|
| **Additive change** | We *add* a new column; we never rename or delete an old one. Nothing existing breaks. |
| **Nullable column** | A column that is allowed to be empty (`NULL`). |
| **Foreign key (FK)** | A database rule: "the value in *this* column must exist in *that* table." It stops orphan links. |
| **Nullable + FK** | A column that may be empty, *but if it holds a value, that value must be real.* (See §C — this is the interesting part.) |
| **Progressive backfill** | Filling missing data slowly, by hand, as a human touches each row — instead of one risky mass update. |

---

## B. Legacy vs. new

**Before:** a DDO belonged to a **Location** (`ddo_master.loc_code`). The add form was
District → Location → name; the table showed Location + District; search was
District → Location.

**After:** a DDO belongs to a **Treasury**. Everywhere Location appeared, Treasury takes
its place:

| Area | Before | After |
|---|---|---|
| Add form | District → **Location** → name | District → **Treasury** → name |
| Table | Code · Name · **Location** · District | Code · Name · **Treasury** · District |
| Search | District → **Location** | District → **Treasury** |
| Model | `location()` | keep it **and add** `treasury()` |
| District comes from | ddo → location → district | ddo → **treasury** → district |

---

## C. The data-model decision — and the nullable-FK idea

We **added** `ddo_master.treasury_code` and **kept `loc_code` exactly as it was**:

```php
$table->string('treasury_code', 10)->nullable();      // ① may be empty
$table->foreign('treasury_code')                       // ② if set, must be real
    ->references('treasury_code')->on('treasury_master')
    ->nullOnDelete();
```

**Why keep `loc_code`?** Phase-A rule: **never destroy legacy data.** The 3,085
DDO→Location links still live in that column; we simply stopped *showing* them. If we
ever need them again (old reports, or the Phase-B cleanup), they're right there.

**How can a column be BOTH a foreign key AND nullable?** Because the two rules answer
*different questions*:

| Value stored | Nullable rule | FK rule | Result |
|---|---|---|---|
| `NULL` (the 3,085 old DDOs) | ✅ allowed | ⏭️ **skipped** — a FK never checks NULL | ✅ stored |
| `'01'` (a real treasury) | ✅ allowed | ✅ found | ✅ stored |
| `'99'` (no such treasury) | ✅ allowed | ❌ not found | ❌ rejected |

A foreign key only checks **non-NULL** values. So "empty" is allowed, but any real value
must point at a real treasury. We *proved* this live: adding the FK to a table with 3,085
NULL rows succeeded without a single complaint.

> **Contrast with "NOT NULL + FK"** (as on `treasury_master.dist_code`): that means "must
> *always* have a value **and** it must exist." Different rule, because a treasury without
> a district makes no sense — but a legacy DDO without a treasury (yet) is fine.

---

## D. The screen logic (same shape, Treasury swapped in)

- **Model** `Ddo` gained `treasury()` (belongsTo) and a `scopeForTreasuryFilter($dist,
  $tsy)`. We **kept** `location()` and its scope too — the retained data still has a valid
  relationship. Note `treasury_code` is `varchar` on *both* `ddo_master` and
  `treasury_master`, so — unlike the old `loc_code` (varchar) vs `loc_master.loc_code`
  (bigint) mismatch — there's **no type-casting dance**. Cleaner.
- **Two cascades** (the pattern you already knew): the **list filter** (District →
  Treasury) and the **add/edit form** (District → Treasury → name). Changing the district
  clears the now-stale treasury in both.
- **Editing an old DDO:** its `treasury_code` is NULL, so the form opens with empty
  District + Treasury (the "— not set —" state). Because treasury is `required`, the admin
  must pick one to save — that's the **progressive backfill** in action. On save we update
  only the name + treasury; **`loc_code` is left untouched.**
- **New DDOs** get a treasury and no location (`loc_code` stays NULL, which is allowed).

---

## E. What to expect right now

- **All 3,085 existing DDOs show "— not set —" for Treasury.** They fill in as they're
  edited (a progressive backfill). There is **no safe automatic fill** — a district has
  *many* treasuries, so nothing can guess which one an old DDO belongs to.
- The District → Treasury dropdown is only as full as **Treasury Master** — you populate
  treasuries there first.

---

## F. How to verify this change yourself

```bash
php artisan test --filter=DdoTest
php artisan test
```

**Result:** the full suite went from **100 → 101 passing / 287 assertions**. The DDO
tests were rewritten to use treasuries, plus a new one proving a legacy DDO (with a
location, no treasury) **keeps its `loc_code`** and gains the treasury you pick on edit.

---

## G. Gotchas worth remembering

- **Change by adding, not renaming.** New `treasury_code`, old `loc_code` preserved → zero
  data loss, old app unaffected.
- **Nullable + FK = "optional, but valid if present."** The FK skips NULLs, which is
  exactly why 3,085 empty rows didn't block the constraint.
- **Matching column types avoids casting.** `treasury_code` is varchar on both sides — no
  coercion needed (unlike the legacy `loc_code`).
- **No automatic backfill for one-to-many parents.** A district has many treasuries, so
  only a human (or an explicit rule) can assign the right treasury to each old DDO.

---

## ✅ DDO Master re-pointed to Treasury: COMPLETE

DDOs now run on Treasury; the 3,085 legacy DDOs backfill one honest edit at a time, with
their old location data safely preserved.
