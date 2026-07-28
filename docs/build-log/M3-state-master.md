# M3 add-on — State in District Master (explained for beginners)

> **What this is, in one sentence:**
> We added a **State** to every district — a brand-new `state_master` table (the 36 Indian
> states/UTs) plus a new `state_code` column on the old `dist_master` table — and made the
> District Master screen show and require a State.

This is the **first time we changed a legacy table** (`dist_master` came straight from the
old CodeIgniter/MySQL app). So this note also teaches how to do that *safely*.

---

## A. Mini-glossary (words used below)

| Word | Plain meaning |
|---|---|
| **Migration** | A PHP file that changes the database *structure* (create a table, add a column). Runs with `php artisan migrate`; can be undone with `migrate:rollback`. |
| **Nullable column** | A column that is allowed to be empty (`NULL`). The opposite is `NOT NULL` (always required). |
| **Foreign key (FK)** | A rule that says "the value in *this* column must exist in *that* table." It stops you storing a `state_code` that isn't a real state. |
| **Seeder** | A PHP file that *inserts data* (not structure). Here: the 36 states. Run with `php artisan db:seed`. |
| **`upsert()`** | "Update or insert." Re-running it never makes duplicates — it matches on the key and refreshes. That makes a seeder safe to run again. |
| **LGD code** | The official Government-of-India *Local Government Directory* number for each state (Arunachal Pradesh = **12**, Manipur = **14**). |
| **Progressive backfill** | Filling in missing old data slowly, by hand, as people touch each row — instead of one risky mass update. |

---

## B. Legacy vs. new — what changed and why

**What was in the legacy app:** `dist_master` had only two columns — `dist_code` and
`dist_name`. There was **no concept of a state**. Every district just floated on its own.

**What was wrong / missing:** the data is really Arunachal-Pradesh-centric, but you couldn't
*prove* which state a district belonged to, and you couldn't group or filter by state.

**What we did in the new app:**
1. Created a `state_master` reference table (36 states, official codes).
2. Added a `state_code` column to `dist_master`, linked to `state_master` by a foreign key.
3. Made the District Master screen show a **State** dropdown (defaults to Arunachal Pradesh)
   and a **State** column in the list + Excel export.

**Why not just backfill every old district to "Arunachal Pradesh"?** Because it isn't true.
When we listed the 30 existing districts we found two odd ones:

- **`1` = IMPHAL** → that's actually **Manipur**, not Arunachal Pradesh.
- **`999` = "Others"** → a placeholder, not a real district at all.

A blanket "set everyone to AP" would have quietly corrupted those two. So instead we chose
the **progressive backfill**: old rows start with **no state**, and an admin picks the right
one the first time they edit each district.

```
   30 legacy districts  ─────────►  state_code = NULL  (unknown, on purpose)
                                        │
              admin opens a district    │  clicks Edit, picks the real state
                                        ▼
                                 state_code saved correctly
   (IMPHAL → Manipur,  a real AP district → Arunachal Pradesh,  "Others" → left alone)
```

---

## C. Piece 1 — the two migrations

### C.1 New table `state_master`

```php
Schema::create('state_master', function (Blueprint $table) {
    $table->integer('state_code')->primary();   // the official LGD code, e.g. 12 = AP
    $table->string('state_name', 100);
});
```

**Why `state_code` is the primary key (and not an auto-number):** the LGD codes are real,
fixed government numbers. If we let the database invent its own 1,2,3… we'd lose the ability
to match any other official dataset. We *own* the real code, so we store the real code.

### C.2 The new column on the legacy table

```php
Schema::table('dist_master', function (Blueprint $table) {
    $table->integer('state_code')->nullable()->after('dist_code');   // ① nullable
    $table->foreign('state_code')                                    // ② real FK
        ->references('state_code')->on('state_master')
        ->nullOnDelete();
});
```

Two deliberate safety choices — this is the important lesson:

- **① `nullable()`** — the 30 existing rows have no state, and the *old CI app* (still alive
  during the rebuild) inserts districts with only code + name. A `NOT NULL` column would
  **crash both**. Nullable keeps everything working. We enforce "you must pick a state" in
  our *form* instead (app-level), and can tighten the DB to `NOT NULL` later in Phase B.
- **② the foreign key** — because *we* own `state_master`, we can guarantee integrity here:
  the database will physically reject a `state_code` that isn't a real state. (`NULL` is
  exempt, so old rows are fine.)

> **Rule of thumb:** *nullable in the database, required in the form.* The DB stays
> compatible with the old world; the app enforces the new rule going forward.

---

## D. Piece 2 — the seeder (the 36 states)

```php
DB::table('state_master')->upsert($rows, ['state_code'], ['state_name']);
```

`upsert` = "insert them, but if a `state_code` already exists, just refresh its name." That
one line is why you can run the seeder ten times and always end with exactly 36 clean rows.

---

## E. Piece 3 — the model + the relationship

`State` maps onto `state_master` (hand-known code → `$incrementing = false`, no timestamps).
Then the two sides of the link:

```php
// State.php  — one state has many districts
public function districts(): HasMany { return $this->hasMany(District::class, 'state_code', 'state_code'); }

// District.php — one district belongs to one state
public function state(): BelongsTo  { return $this->belongsTo(State::class, 'state_code', 'state_code'); }
```

This is the exact same "belongsTo" pattern you already met with **Location → District**. In
the list we call `->with('state')` (eager loading) so showing 25 state names costs **1 extra
query, not 25** (the classic *N+1* trap).

---

## F. Piece 4 — the screen behaviour (your two cases)

**Case 1 — NEW district:** the form field `state_code` is declared with a default of `'12'`,
so the dropdown opens **pre-selected to Arunachal Pradesh**. The admin can change it, then
types the code + name and saves.

```php
public string $state_code = '12';   // '12' = Arunachal Pradesh
```

**Case 2 — EDIT an old district:** the row has `NULL`, so `edit()` sets the field to `''`
and the dropdown shows the "— Select state —" placeholder. Because the rule is
`required`, the admin **must** choose a state before it will save — which is precisely how
the old rows get cleaned up.

```php
'state_code' => ['required', 'integer', 'exists:state_master,state_code'],
```

`exists:state_master,state_code` = "whatever they picked must be a genuine row in
`state_master`." Belt-and-braces with the database foreign key.

---

## G. Commands we ran

```bash
# 1. apply the two new migrations to Postgres
php artisan migrate

# 2. load the 36 states
php artisan db:seed --class=StateMasterSeeder

# 3. sanity check (36 states in, all 30 districts still NULL by design)
php artisan tinker --execute="echo App\Models\State::count();"

# 4. the safety net — everything still green
php artisan test
```

---

## H. Verification

```text
before this feature:  89 passed / 246 assertions
after  this feature:  90 passed / 252 assertions
```

The extra test is `test_state_is_required`; two existing tests were upgraded to prove the
AP-default (create) and the pick-a-state-on-edit flow.

---

## I. Gotchas worth remembering

- **`state_master` is Phase-A-safe, but the `dist_master` column is our first legacy change.**
  We kept it non-breaking by making it nullable + keeping the old app working.
- **Don't mass-backfill dirty data.** IMPHAL (Manipur) and "Others" (999) proved why. A human
  picking each state is slower but *correct*.
- **The IDE will scream red on this feature and be wrong.** intelephense flags PHP 8 syntax
  (named arguments, constructor promotion, the `?->` null-safe operator) as errors. Every file
  passed `php -l` cleanly. When in doubt, trust `php -l`, not the squiggles.
- **`state_code` is `integer`, not `bigint`** — state codes are tiny (1–38). It still links
  cleanly to `dist_master` because both sides are `integer`.

---

## ✅ Done. Old districts now acquire their real state one honest edit at a time.
