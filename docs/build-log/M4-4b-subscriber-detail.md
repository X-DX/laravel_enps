# M4 · slice 4b — Subscriber detail page

> **What this is, in one sentence:**
> Click a subscriber in the list → a page opens showing *all* their details, matching the
> legacy "Issue Account" form field-for-field.

Built hand-typed, one concept at a time.

---

## A. The new idea: a page for ONE record (route-model binding)

4a showed a list. 4b shows **one** subscriber. We can't hand-make 33,594 pages, so the
subscriber's id goes in the URL: `/accounts/5`.

The `5` is a **route parameter** (a slot in the address). The route is written
`/accounts/{subscriber}`, and the component asks for a `Subscriber`:

```php
public function mount(Subscriber $subscriber)
```

That triggers **route-model binding** — Laravel reads the id, fetches that Subscriber, and
hands it over. A bad id → automatic **404**. We never write the lookup.

We store only `$subscriberId` (a number) on the component and re-load the record in
`render()` — light and safe, since Livewire passes component state back and forth.

---

## B. Getting the fields right (read the legacy form!)

The first draft missed fields and mislabeled others. Reading the legacy
`issueAccountNo.php` form gave the exact labels and columns:

| Detail | Column | Note |
|---|---|---|
| Employee Name | `name` | |
| Father / Mother | `father_name` / `mother_name` | + Single Mother flag |
| Appointment Order / **Date** | `appnt_ord_no` / `doapptorder` | |
| DOB / Joining / Retirement | `dob` / `doj` / `dor` | |
| Designation / Department | `designation` / `nameofdept` | |
| **Office Location** | *(none — via the DDO)* | there is **no** location column; it comes from `ddo → location` |
| DDO | `ddocode` | |
| **Pension Type** | `pension_type` | `N` → NPS, `U` → UPS |
| **Basic + DA** (NPS) / **Basic Pay** (UPS) | `pay` | the *label* depends on pension type |
| **Deduction Start Month / Year** | `starting_month` / `starting_fin_year` | month `"05"` → "May" |
| 1st / 2nd / 3rd Nominee | `name_nominee` / `2` / `3` | |
| PRAN / PPAN | `pran_no.pran_no` / `ppan_no` | |

Three things worth remembering:
1. **Office location comes from the DDO.** The subscriber has no location column. Every DDO
   sits in a location, so we follow `subscriber → ddo → location → loc_name`. That's why
   `render()` eager-loads `ddo.location` (one dot loads the DDO *and* its location).
2. **The pay label is data-driven.** `N` → the number means "Basic + DA"; `U` → "Basic Pay".
   We use the computed label as the array *key*, so it changes per subscriber.
3. **Codes → words.** `pension_type` (`N`/`U`) → NPS/UPS; `starting_month` (`"05"`) → "May".

---

## C. How the page is built

The view builds a `$sections` list — a list of cards (Personal, Service, Pension & Pay,
Nominees, Account), each a set of `label => value` pairs — then a **loop** draws each card
and each field. Add a field later = add one line to the list, no copy-paste.

A few date columns (`dor`, `doapptorder`, `entry_date`, `finalize_date`) were added to the
`Subscriber` `casts` so they come out as date objects and can be formatted `d-m-Y`.

The list's Name column is now a link (`route('accounts.show', $sub->id)` + `wire:navigate`).
Route `accounts.show` (`/accounts/{subscriber}`) sits behind the same
`entrysection.view_all_accounts` permission.

---

## D. Verification

```text
before 4b:  112 passed / 319 assertions
after  4b:  117 passed / 343 assertions   (+5 ShowSubscriberTest)
```

Tests cover: the permission gate, all the key detail fields (incl. office-location-via-DDO,
NPS "Basic + DA", month name, nominees, PRAN/PPAN), the UPS "Basic Pay" flip, and the
automatic 404 for a missing id.

---

## E. Next in M4

4c register a new subscriber (draft) · **4d finalize + allot the account number (the counter
— transaction + row lock, Service class)** · 4e edit / close.
