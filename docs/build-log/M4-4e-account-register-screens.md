# M4 · Slice 4e — The three Account Register screens (+ PDF & Delete) — Explained for Beginners

> **What this slice gives you, in one sentence:**
> The legacy's three separate screens — **View All Accounts**, **Pending Issue Accounts**,
> **Finalized Issued Account** — now exist as three menu items, each with the right buttons,
> all powered by **one** Livewire component.

This slice fixes an earlier over-consolidation (we'd merged the three screens into one *and*
put Finalize on the read-only "View All"), and completes the Pending action bar with **PDF**
and **Delete**.

---

## A. Why three screens, not one

The legacy separates a **safe read-only view** from the **action** screens on purpose:

| Screen (legacy menu) | Permission | Buttons |
|---|---|---|
| **View All Accounts** (154) | `view_all_accounts` | Status filter · Excel · PDF *(read-only)* |
| **Pending Issue Accounts** (155) | `pending_issue_accounts` | Excel · PDF · **Finalize** · **Delete** + checkboxes |
| **Finalized Issued Account** (156) | `finalized_issued_account` | Excel · PDF *(read-only)* |

Mixing Finalize into "View All" meant a page called *view* could *change* data — the wrong
mental model. Now finalizing/deleting live only on **Pending**, where drafts belong.

---

## B. One component, three screens — the "mode" trick

Writing three near-identical list screens would be duplication. Instead, **one** component
(`Subscribers`) serves all three, told apart by a value called **`mode`** that comes from the
**route**:

```php
// routes/web.php
Route::get('/accounts',           Subscribers::class)->defaults('mode', 'all')      ->name('accounts.index');
Route::get('/accounts/pending',   Subscribers::class)->defaults('mode', 'pending')  ->name('accounts.pending');
Route::get('/accounts/finalized', Subscribers::class)->defaults('mode', 'finalized')->name('accounts.finalized');
```

`->defaults('mode', 'pending')` fixes a route parameter, and Livewire hands it to `mount()`:

```php
public function mount(string $mode = 'all'): void
{
    $this->mode = $mode;
    $this->authorize(self::ABILITIES[$mode]);   // each screen guards itself
}
```

Everything else keys off `$mode`:
- **Which rows to show** — `effectiveStatus()` returns `'T'` on Pending, `'F'` on Finalized,
  and on "all" it returns the **status dropdown** value (so View All can still be narrowed).
- **Which buttons appear** — the Blade view shows checkboxes + Finalize + Delete only
  `@if ($mode === 'pending')`; the status dropdown only `@if ($mode === 'all')`.
- **Which permission is required** — `mount()` and the route middleware both use the
  per-mode ability.

> **Why route defaults and not a `{mode}` URL segment?** We don't want `/accounts/all` in the
> address bar. `->defaults()` sets the value *behind* the URL, so each screen keeps a clean,
> memorable path and its own name.

---

## C. PDF — a new export (DomPDF)

Excel came from `maatwebsite/excel`; PDF is new, so we installed **`barryvdh/laravel-dompdf`**:

```bash
composer require barryvdh/laravel-dompdf
```

DomPDF turns an **HTML view** into a PDF. It does **not** run Tailwind, so the template
`resources/views/pdf/subscribers.blade.php` uses plain inline CSS. The component renders it and
streams it back as a download:

```php
$pdf = Pdf::loadView('pdf.subscribers', [...])->setPaper('a4', 'landscape');

return response()->streamDownload(fn () => print($pdf->output()), $filename, [
    'Content-Type' => 'application/pdf',
]);
```

The PDF exports the **currently filtered list** (search + status), the same rows you see —
consistent on all three screens. (Excel does the same; PDF is just the printable version.)

---

## D. Delete — pending drafts only

`deleteSelected()` removes ticked rows, but with a hard guardrail — it can **only** delete
drafts:

```php
$deleted = Subscriber::where('save_flag', 'T')   // ← never touches a finalized account
    ->whereIn('id', $this->selected)
    ->delete();
```

Even if a finalized row's id were somehow in `$selected`, the `where('save_flag','T')` filter
skips it. It's `wire:confirm`-guarded and gated by `pending_issue_accounts`.

---

## E. How to verify yourself

```bash
php artisan route:list | grep accounts     # index · pending · finalized · issue · show
php artisan test
```

Visit **/accounts** (status filter + Excel + PDF, no checkboxes), **/accounts/pending**
(checkboxes + Finalize + Delete + Excel + PDF), **/accounts/finalized** (Excel + PDF).

**Result:** full suite **131 → 136 passing / 382 assertions**. New tests: pending mode shows
only pending, finalized mode shows only finalized, the list downloads as a PDF, delete removes
pending drafts, and **delete never removes a finalized account**.

---

## F. Gotchas worth remembering

- **Separate the read-only view from the action screens.** A page named "view" should not
  mutate data.
- **One component, many screens** via a route `->defaults(...)` value read in `mount()` —
  DRY without hiding the screens from the user (still three menu items, three URLs).
- **DomPDF ≠ Tailwind.** PDF templates need plain inline CSS.
- **Stream PDFs from Livewire** with `response()->streamDownload(...)`.
- **Destructive actions need a data-level guardrail**, not just a hidden button — `Delete`
  filters to `save_flag = 'T'` so a finalized account can never be removed.

---

## ✅ 4e — Account Register screens: COMPLETE

Three faithful screens, one component; the Pending action bar is complete (Excel · PDF ·
Finalize · Delete). **Next: Edit issued Account + Close Account** (menus 153 & 234).
