# M6 · First Register — Explained for Beginners

> **What this module is:** the **money on-ramp** — record each incoming receipt/draft a DDO
> deposits (pooled contributions), before it's split into individual contributions (M7).

Legacy Entry Section → **First Register** (menus 171–174). Built the same way as Account
Register: one entry form + three list screens (View All / Pending / Finalized).

## Slice 6.1a — entry form + the three lists ✅

**The `first_receipt` table** (274K rows) — one row per deposit. Key columns: `sl_no` (PK),
`draft_no`/`draft_date`, `order_no`/`order_date`, `amount`, `ddocode`, `type` (D/R),
`draw_bank_code`, `purpose`, `contribution_type`, `pension_type`, and **`flag`** — the lifecycle:

```
T  = pending        (a fresh entry)
CR = finalized      (what our Finalize writes; legacy also has FZ)
E  = exported       (later, beyond this module)
```

So **Pending = flag `T`**, **Finalized = flag `FZ` or `CR`**.

### The Entry form (menu 171)
Fields and where each dropdown comes from:

| Field | Source |
|---|---|
| **Office Location** | Location Master → **filters the DDO list** (cascade) |
| **DDO** | DDO Master, `where loc_code = <location>` |
| **Draw Bank** | Bank Master |
| **Purpose** | `purpose_master_codes` (D01 = "Deduction for Jan"…) |
| Order/Letter No + Date, Draft/Receipt No + Date, Amount | typed |
| Draft or Receipt? | a checkbox → `type` = D or R |
| Contribution Type | SC = Single · DC = Double |
| Pension Type | NPS / UPS |

Saving writes `flag = 'T'` (pending). A **duplicate guard** (same *draft number + date*) blocks
the save and reveals a **"Save anyway"** button — mirroring the legacy force-save.

> **Nice contrast:** the Account form's DDO cascade filters by *treasury* (data mostly empty).
> First Register filters by **location** — and `ddo_master.loc_code` is populated (3083/3085), so
> **this cascade actually works** with real data.

### The three lists (menus 172–174)
One `FirstEntries` component, three routes (a `mode`): **View All** (status filter · Excel · PDF),
**Pending** (`flag='T'`), **Finalized** (`flag` in FZ/CR) — search, per-page, Excel, PDF. The
sidebar's **Pending First Entry** now carries a live count badge.

### Verify
```bash
php artisan test --filter=FirstRegisterTest
```
7 tests — entry permission, the location→DDO cascade, a save with the system fields, the
duplicate "Save anyway" guard, list permission, pending/finalized flag filtering, and the View
All status filter. Full suite: **164 → 171 passing**.

## Slice 6.1b — Pending actions (next)
Finalize (`flag → CR` + date), Delete (drafts only), and per-row **Edit** (all fields), reusing
the entry form in edit mode.

## Gotchas
- **A `flag` with more than two states** — don't assume T/F; "finalized" here means FZ *or* CR.
- **Cascade on the field the data actually populates** — location for DDOs here, not treasury.
- **Duplicate guard + deliberate override** — block the accidental re-entry, allow the intended one.
