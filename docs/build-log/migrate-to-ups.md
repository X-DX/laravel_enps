# Migration to UPS — Explained for Beginners

> **What this gives you, in one sentence:**
> Move a finalized **NPS** account to the **Unified Pension Scheme (UPS)** — flipping its
> pension type and recording the migration.

Legacy menu **161** (Entry Section → Account Register). The legacy code and the `ups_migration`
table existed, but the **menu item + permission were never imported**, so it had silently
disappeared from the new system.

## A. What it does

1. **Search** a finalized account (by number or name) → shows **Name** + **Pension Type**.
2. Pick a **Migration Year** (2024 → now) and **Month** (capped at the current month for the
   current year).
3. **Migrate** → in **one transaction**: set `allotment_accnt_no.pension_type = 'U'` **and**
   insert a row into `ups_migration` (user, account, year, month, date).

**Rules:** only an **NPS (`N`)** account can migrate; a **`U`** account is "already migrated";
the guard on the update (`WHERE pension_type = 'N'`) makes a double-migrate impossible.

## B. Why the menu item was missing (and the fix)

The sidebar is **data-driven** — it renders `menu_items ⋈ permissions` on `legacy_menu_id`.
Menu 161's rows were absent, so nothing could show. A migration
(`add_migration_to_ups_menu`) inserts them **idempotently** (`updateOrInsert`):

- `menu_items`: `menu_id=161, menu='entrysection', sub_menu='accountregister', menu_label='Migration to UPS'`
- `permissions`: `key='entrysection.migration_to_ups', legacy_menu_id=161`

Once both exist, the item appears under **Account Register** for anyone holding the permission
(admins bypass). `SidebarMenu::ROUTES` maps the key to the new `accounts.ups-migration` route,
and `iconFor()` gained a `migrat → arrows-right-left` rule so it gets a swap icon.

## C. The build

- **`MigrateToUps`** Livewire component — live account search, an NPS/UPS pension badge, a
  year→month cascade, and the transactional `migrate()` (update + log, guarded).
- **View** `migrate-to-ups.blade.php` — search panel + details/migrate panel; an amber
  "already migrated" notice replaces the form for `U` accounts.
- The existing `ups_migration` table is reused (no schema change); the log insert uses
  `DB::table` since that table has no primary key.

## D. Verify

```bash
php artisan migrate            # adds the menu item + permission (161)
php artisan test --filter=MigrateToUpsTest
```

**Result:** 5 tests — permission gate, search, year/month required, an NPS account migrates
(sets `U` + logs the row), and an already-UPS account is refused (nothing logged). Full suite:
**159 → 164 passing**.

## E. Gotchas

- **A data-driven menu needs its data.** Working code + table aren't enough — the `menu_items`
  and `permissions` rows must exist for the sidebar to show a feature.
- **Guard the state change at the WHERE clause** (`pension_type = 'N'`) so a migrate can't run
  twice, even under a race.
- **Update + log belong in one transaction** — the account flip and its audit row land together
  or not at all.

---

## Update (2026-08-25) — Migrate to UPS is cross-operator

Row-level ownership (`OwnedByUser` on `Subscriber`) was silently narrowing every query on this
screen to the logged-in operator's own accounts. For a non-admin the account search returned nothing, and `migrate()`'s guarded mass UPDATE flipped 0 rows and
reported *"Could not migrate"* while nothing had happened.

Roughly 17,400 migrated accounts carry no `user_id` at all, so they were invisible to every
non-admin regardless. The legacy never filtered this screen by user.

**Fix:** every `Subscriber::` query here now goes through `Subscriber::acrossOperators()`
(plus `openFinalized()` where the finalized-and-open rule applies). Admins are unaffected.
Full reasoning, and the rule it taught us, in
[row-level-ownership.md](row-level-ownership.md).
