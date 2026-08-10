# M4 · Slice 4b — Subscriber Detail Page — Explained for Beginners

> **What this slice gives you, in one sentence:**
> Click a subscriber in the list and a **profile page** opens showing *everything*
> about that one person — matching the legacy "Issue Account" form field-for-field.

4a showed a *list* (a few columns per row). 4b shows **one** subscriber with all ~25
fields grouped into cards. It's still **read-only** — no editing yet.

Read section **A (the new idea)** first if route parameters are new to you.

---

## A. The new idea — a page that shows ONE record (route-model binding)

Every screen so far was "one web address = one page" (`/accounts` = the list). Now we
need a page for **one** subscriber — but there are 33,594 of them. We obviously can't
hand-make that many pages.

The trick: put the subscriber's **id number inside the web address**:

```text
/accounts/1     → the subscriber whose id is 1
/accounts/5     → the subscriber whose id is 5
```

The address keeps the same shape, `/accounts/{something}`, and only the number changes.
That changing number is a **route parameter** — a blank slot the URL fills in.

### How Laravel fetches the right subscriber for you

Normally you'd write four steps: read the number, query the database, handle "not
found". Laravel does all of it automatically — this is **route-model binding**. You:

1. write the route with a named slot: `/accounts/{subscriber}`, and
2. ask for a `Subscriber` in the component's `mount()` method.

```php
public function mount(Subscriber $subscriber): void
{
    $this->authorize('entrysection.view_all_accounts');
    $this->subscriberId = $subscriber->id;   // keep just the id number
}
```

Because you asked for a `Subscriber`, Laravel reads the id from the URL, fetches that
row, and hands it to you. **If no subscriber has that id, Laravel shows a 404 "not
found" page automatically** — you write none of that.

> **Why store only the id, not the whole record?** A Livewire page talks to the server
> repeatedly as you interact, and passing a whole database object back and forth each
> time is heavy and fragile. We keep the lightweight `id` number and re-load the fresh
> record inside `render()`.

```php
public function render()
{
    $subscriber = Subscriber::with(['ddo.treasury', 'designationMaster', 'pran'])
        ->findOrFail($this->subscriberId);

    return view('livewire.accounts.show-subscriber', [
        'subscriber' => $subscriber,
        'department' => Department::find(trim($subscriber->nameofdept)),
    ]);
}
```

Note `->with(['ddo.treasury', …])`: the single dot means "load the DDO **and** that
DDO's treasury" — needed for the Treasury Location field (below).

---

## B. Getting every field right — read the legacy form!

The first draft of this page *missed fields and mislabelled others*. The fix was to
open the legacy entry form (`application/views/entry_section/issueAccountNo.php`) and
copy its exact labels and columns. Here's the full, correct set:

| Label on the page | Column | Note |
|---|---|---|
| Employee Name | `name` | |
| Father's / Mother's Name | `father_name` / `mother_name` | + a Single Mother yes/no |
| Appointment Order | `appnt_ord_no` | |
| **Appointment Date** | `doapptorder` | a date |
| Date of Birth / Joining / Retirement | `dob` / `doj` / `dor` | dates |
| Designation | `designation` | via `designationMaster` |
| Department | `nameofdept` | via the trimmed-code lookup |
| **Treasury Location** | *(via the DDO)* | see §C |
| DDO | `ddocode` | |
| **Pension Type** | `pension_type` | `N` → **NPS**, `U` → **UPS** |
| **Basic + DA** (NPS) / **Basic Pay** (UPS) | `pay` | the *label* depends on pension type |
| **Deduction Start Month / Year** | `starting_month` / `starting_fin_year` | month `"05"` → "May" |
| 1st / 2nd / 3rd Nominee | `name_nominee` / `2` / `3` | |
| PRAN / PPAN | `pran_no.pran_no` / `ppan_no` | |
| Active / Entered By / Entry & Finalize dates | `isactive` / `user_id` / `entry_date` / `finalize_date` | |

Three of these need real thought:

1. **Pension type is a single letter → a word.** The database stores `N` or `U`. A
   `match()` turns `N` into "NPS" and `U` into "UPS".
2. **The pay label is data-driven.** For **NPS** the pay figure means "Basic + DA"; for
   **UPS** it's plain "Basic Pay". So we compute the label from `pension_type` and use it
   as the array *key*, so the label itself changes per subscriber.
3. **The month is a number → a name.** `starting_month` is saved as `"05"`; a small
   `["05" => "May", …]` map turns it into "May".

---

## C. Treasury Location — why it comes *through* the DDO

There is **no location column on the subscriber**. Instead, every **DDO belongs to a
treasury** (set up in the M3 DDO→Treasury change). So a subscriber's "Treasury Location"
is simply **their DDO's treasury**:

```text
subscriber → ddo → treasury → treasury_name
```

That's exactly why `render()` eager-loads `ddo.treasury`. Most existing subscribers show
**"—"** here, because their DDO hasn't been linked to a treasury yet (that link is being
filled in gradually). When a DDO gets a treasury, its subscribers' Treasury Location
appears automatically.

> **History:** this field was first built as "Office Location" (read through the DDO's
> *location*). It was later relabelled to **Treasury Location** and re-pointed at the
> DDO's *treasury*, to stay consistent with the DDO→Treasury change.

---

## D. How the page is drawn (the loop pattern)

A subscriber has ~25 fields. Writing 25 near-identical HTML blocks by hand would be a
lot of copy-paste and easy to get wrong. So instead:

1. We build a **list** of the fields — grouped into sections (Personal, Service,
   Pension & Pay, Nominees, Account). Each entry is a `label => value` pair.
2. We write the HTML for *one* card and *one* field **once**, and a **loop**
   (`@foreach`) stamps them out for every section and every field.

To add a field later you just add one line to the list — no new markup.

The page has a **header card** (an avatar with the person's initials, name, a status
badge, and a quick-facts strip showing Account No / PRAN / DDO), then the section cards
below.

A few date columns (`dor`, `doapptorder`, `entry_date`, `finalize_date`) were added to
the `Subscriber` `casts` so they come out as date objects and can be formatted `d-m-Y`.
The list's Name column is now a link (`route('accounts.show', $sub->id)` +
`wire:navigate`) that opens this page.

---

## E. How to verify 4b yourself

```bash
php artisan test --filter=ShowSubscriberTest
php artisan test
```

**Result:** 5 feature tests; the full suite went from **112 → 117 passing / 343
assertions**. The tests check: the permission gate, every key field (including Treasury
Location via the DDO's treasury, the NPS "Basic + DA" label, the month name, and the
nominees), the UPS "Basic Pay" flip, and the automatic **404** when someone visits an id
that doesn't exist.

---

## F. Gotchas worth remembering

- **Route-model binding = free lookup + free 404.** Type-hint the model in `mount()` and
  Laravel does the rest.
- **Read the legacy form before building an entry/detail screen** — it's the source of
  truth for the exact fields and labels.
- **A "location" can be a *derived* value.** The subscriber has no location column; it
  comes through the DDO. Follow the chain (`ddo.treasury`) and eager-load it.
- **Labels can be data-driven** — the pay label ("Basic + DA" vs "Basic Pay") depends on
  the pension type. Compute it, then use it as the field key.

---

## ✅ 4b — Subscriber detail: COMPLETE

A full, polished, read-only profile of one subscriber. **Next: 4c — the entry form to
*register* a new subscriber (our first write screen).**
