# Row-Level Ownership (per-user data visibility)

**Type:** cross-cutting security feature (not a milestone slice)
**Applies to:** Account Register (`allotment_accnt_no`) and First Register (`first_receipt`) — and every future module that opts in.

---

## What

Each record now **belongs to the user who created it**. A user sees only their own rows.

- User1's entries are invisible to User2, and vice-versa.
- This holds on **every** screen: lists, search, Excel, PDF, the detail page, the edit page, finalize/delete, the sidebar "pending" badges, and the dashboard numbers.
- **Admins** (`role_flag = 'A'`, i.e. `User::isAdmin()`) are the one exception — they see everyone's rows.

## Why

The legacy app already worked this way. Every legacy list query carried
`where user_id = <session user>` (see `application/controllers/EntrySection/FirstEntry.php`
and `application/models/EntrySection/EmployeeModel.php`). Our new lists were showing
**all** rows to **all** users — a real data leak. The legacy also had a privileged role
(`role_flag == 'U'`) that could see everything in a few screens; we map that idea onto our
existing admin (`'A'`).

## How — one reusable mechanism

Instead of hand-adding `->where('user_id', ...)` to dozens of queries (easy to forget → a leak),
we use a **global scope** applied by a **trait**. Add the trait to a model and *every* Eloquent
query on it is filtered automatically.

### Files

| File | Role |
|------|------|
| `app/Models/Scopes/OwnedByUserScope.php` | The global scope. On every query: if there's an authenticated non-admin, add `where user_id = auth id`. Skips when unauthenticated (console/seeders) or admin. |
| `app/Models/Concerns/OwnedByUser.php` | The trait. Registers the scope **and** stamps `user_id = auth id` when a new row is created (so the form never has to send it). |
| `app/Models/FirstReceipt.php` | `use OwnedByUser;` |
| `app/Models/Subscriber.php` | `use OwnedByUser;` |
| `app/Support/Navigation/SidebarMenu.php` | Pending badges use raw `DB::table()` (a global scope can't reach those), so they scope by user by hand — `pendingCount()`. |
| `app/Livewire/Dashboard.php` | Same: dashboard counts use raw `DB::table()`, scoped by hand via `ownedTable()`. |

### The big security win: route-model binding

Because the scope hooks *every* Eloquent query, it also filters route-model binding.
So `GET /first-register/{id}` for **someone else's** id resolves to **null → 404**.
A non-owner can't even confirm the record exists. No extra `if` needed in the controller.

## Key decisions

1. **Global scope + trait, not manual `where`s.** DRY, and impossible to forget on a new query.
   Future modules get per-user isolation by adding one line: `use OwnedByUser;`.
2. **Admins bypass** (decision: *per-user, but admins see all*). Reuses the existing
   `User::isAdmin()` (`role_flag = 'A'`). To let another role see all later, that's a one-line
   change in the scope.
3. **Dashboard + badges scoped too** (decision: *yes, consistent*). Otherwise a badge could say
   "5 pending" while the list shows 2. These use raw SQL, so they're scoped by hand.
4. **Owner stamped by the trait**, not the form. `FirstReceipt::create()` no longer passes
   `user_id` — the `creating` hook sets it. One source of truth.
5. **Escape hatch:** `Model::withoutGlobalScope(OwnedByUserScope::class)` for a deliberate
   cross-user query (e.g. a future admin-wide report).

## Consequences to be aware of

- **404 vs 403 order.** A user with *no permission* who also doesn't own the record now hits the
  404 (binding) before the 403 (permission). The "forbidden" feature tests were updated to seed a
  record the acting user **owns**, so they still test the permission gate (403) in isolation.
- **Cross-user operations are per-user for non-admins — confirmed decision (2026-08-25).**
  Close Account, Assign PRAN and Migrate-to-UPS search only the current user's accounts (admins
  still see all). This is intentional and diverges from the legacy, whose by-account-number
  lookups had no user filter. Locked in by
  `SubscriberTest::test_an_account_number_lookup_is_scoped_for_non_admins`. If a workflow ever
  needs a non-admin to act on another user's account, make them admin or relax that one screen
  with `withoutGlobalScope`.
- **Duplicate checks are per-user.** The First Register "duplicate draft" guard now only looks at
  the current user's own drafts — consistent with the isolation.

## Verification

- New tests:
  - `FirstRegisterTest::test_entries_are_scoped_to_their_owner_for_non_admins`
  - `FirstRegisterTest::test_a_new_entry_is_stamped_with_the_current_user`
  - `SubscriberTest::test_subscribers_are_scoped_to_their_owner_for_non_admins`
- Updated 4 "forbidden" tests to own the record so they still assert **403**.
- Full suite: **185 passing**.

## Gotchas

- **Raw `DB::table()` queries are NOT covered** by the Eloquent global scope. Anything using the
  query builder directly (badges, dashboard, future reports) must scope by hand. Grep for
  `DB::table(` when auditing.
- **Queued jobs / exports:** the scope reads `auth()`. Our exports stream synchronously (auth is
  present). If an export is ever queued, capture the user id and filter explicitly.
- **Seeders/console:** unauthenticated → scope is skipped → they see all rows (intended).
