# M4 · Slice 4g — Edit an account — Explained for Beginners

> **What this slice gives you, in one sentence:**
> An **Edit** button on each Pending / Finalized row that opens the Issue form pre-filled — and
> for a finalized account, the fields that define its account number are **frozen**.

---

## A. The design in one idea: reuse the form, add an "edit mode"

The Edit screen *is* the Issue form (4c) — same fields, same cascades — just:
- pre-filled from an existing subscriber, and
- saved with an **UPDATE** instead of an **INSERT**.

So instead of a second, near-identical component, the **`IssueAccount` component serves both**.
A single flag decides which mode it's in:

```php
public ?int $editingId = null;   // null = issuing new · set = editing this one

public function mount(?Subscriber $subscriber = null): void
{
    if ($subscriber?->exists) {
        $this->authorize('entrysection.edit_issued_account');
        $this->loadForEdit($subscriber);      // pre-fill every field
    } else {
        $this->authorize('entrysection.issue_account');
    }
    // ...
}
```

The route hands the component a subscriber via **route-model binding**:

```php
Route::get('/accounts/{subscriber}/edit', IssueAccount::class)
    ->whereNumber('subscriber')
    ->middleware('can:entrysection.edit_issued_account')
    ->name('accounts.edit');
```

`save()` simply branches: `editingId` set → update; otherwise → the original create path.

---

## B. The business rule: a finalized account freezes 3 fields

An account number is built from **department + pension type** (`AP/NPS/15/0042`). If you let
someone change those *after* the number exists, the number becomes a lie. So for a **finalized**
account we freeze:

- **Department**
- **Pension Type**
- **Account No** (it's display-only anyway)

Everything else (name, nominees, pay, DDO, dates…) stays editable. A **pending draft** has no
number yet, so it can change everything.

Two layers enforce this — belt *and* braces:

1. **UI:** the Department dropdown and Pension radios are `:disabled` when `$isFinalized`.
2. **Server:** before saving, we *snap those fields back* to the stored values, so even a tampered
   request can't change them:

```php
if ($this->isFinalized) {
    $this->nameofdept = trim((string) $subscriber->nameofdept);   // ignore any change
    $this->pension_type = $subscriber->pension_type ?: 'N';
}
$this->validate();
$subscriber->update([...]);
```

> **Why snap-back and not just skip validation?** A disabled input can still be forged in a
> request. Resetting to the DB value means the lock holds no matter what arrives — and validation
> still passes because the value is the (valid) original.

---

## C. Where Edit lives now (not a menu item)

The legacy had a standalone **"Edit issued Account"** menu item (153). We removed it — editing is
now a **per-row action** at the end of the Pending and Finalized tables, which is where you're
already looking at the account. The menu item is hidden via a small `HIDDEN` list in
`SidebarMenu` (the permission still exists — it guards the edit action and the route):

```php
private const HIDDEN = ['entrysection.edit_issued_account'];
// ...skipped in the sidebar loop, so it never renders as a menu item.
```

The per-row button appears only on Pending / Finalized, and only for users who hold
`edit_issued_account`.

---

## D. How to verify yourself

```bash
php artisan test --filter=EditAccountTest
```

**Result:** 4 tests — the form pre-fills from the subscriber; a **pending** draft can change
department + pension; a **finalized** account **freezes** department + pension (name still
changes, dept/pension/number don't); and the route is forbidden without the permission. Full
suite: **146 passing**.

Then: open **Pending Issue Accounts** or **Finalized Issued Account**, click **Edit** on a row.
On a finalized account you'll see Department / Pension / Account No marked **(locked)**.

---

## E. Gotchas worth remembering

- **Reuse the form for edit** via an `editingId` flag + route-model binding — one component, two
  modes, no duplicated 20-field form.
- **Freeze fields that derive a stored identity** (the account number) once that identity exists.
- **Lock in the UI *and* on the server** — a disabled input is a hint, not a guarantee; snap the
  value back before saving.
- **An action can live on a row, not only in the menu** — hide the standalone menu item but keep
  the permission (it still guards the action).

---

## ✅ 4g — Edit an account: COMPLETE

Editing reuses the Issue form, per-row, with finalized accounts protecting the fields behind
their account number. **This completes M4 (Subscriber & Account).**
