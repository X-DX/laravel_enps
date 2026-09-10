# M6 — Central Register (menus 201–206)

Stage 2 of the money-in pipeline. First Register records a draft/receipt as it arrives
(`flag` `T` → `CR`). The **Central Register** gives each `CR`-stage receipt an official
**CR receipt number**, moves it to `FZ`, and lets the office freeze ("block") an entry that
is under objection. See [M6-central-register-numbering.md](M6-central-register-numbering.md)
for the deep-dive on the three identifiers and the duplicate-CR-No hardening.

## The screens

| Menu | Screen | Component | What it does |
|------|--------|-----------|--------------|
| 201 | Entry CR | `EntryCr` | Batch-generate CR numbers for pending (`CR`) receipts → `FZ`. Optional "attach to an existing receipt no". |
| 202 | View All CR Entries | `CrEntries` (`all`) | Browse `CR`+`FZ`, status filter, CR Receipt No column. |
| 203 | Pending CR Entries | `CrEntries` (`pending`) | `CR` only (read-only view of the Entry CR worklist). |
| 204 | Finalized CR Entries | `CrEntries` (`finalized`) | `FZ` only, with CR Receipt No. |
| 205 | Block CR Entry | `BlockCr` (`block`) | List finalized, not-yet-blocked CR rows; **Block** one with a reason. |
| 206 | Blocked CR List | `BlockCr` (`blocked`) | List blocked rows; **Unblock** or **Edit reason**. |

## Entry CR — generation (201)

The heart of the module. `generate()` runs one transaction:
1. `lockForUpdate()` on the single `counter_centralreg` row — prevents two operators grabbing the
   same number (the legacy race; also hardened at the DB with a PK — see the numbering doc).
2. For each ticked receipt: insert `central_reg` (+ `central_reg_entry_date`), copying the
   NOT-NULL ledger fields (`order_no`, `draft_no`, `amount`) from the first receipt.
3. Advance the counter — the CR serial always moves; the **receipt number** only when auto.
4. Flip the receipts `CR` → `FZ`.

**Attach to an existing receipt no** (legacy `e_receipt_no`): file a late/extra draft under a
receipt number you already own, instead of consuming a new one. Live-validated against
`central_reg` (must exist and be yours). Blank = auto.

## Blocking (205–206)

Blocking **freezes one Central Register row**, addressed by its **CR No** (`central_reg.sl_no`,
now a PK), with a reason. It never deletes — a statutory register keeps every entry.
`blocked_cr` / `blocked_reason` / `blocked_date` / `blocked_by_user`.

- The screens are **central_reg-centric** (one row per CR No) because a double-booked draft has
  more than one CR No and you must block the exact one.
- Updates are **guarded + owner-scoped**: `where sl_no = ? and blocked_cr = <expected> [and user_id = me]`,
  then the affected-row count is checked — a stale click or someone else's row changes nothing.
- A reason **modal** (block / edit) with validation; **Unblock** clears all four columns.

## Key decisions

1. **Mode-driven components.** `CrEntries` (3 screens) and `BlockCr` (2 screens) each drive their
   modes off one class + route `defaults('mode', …)` — same pattern as `FirstEntries`. Less code,
   one place to fix.
2. **Edit reuses the First Register form.** A CR entry *is* a `first_receipt` row; the Edit action
   on a pending row links to `first-entries.edit` rather than duplicating the form in a modal (DRY).
   Only pending (`CR`) rows are editable — a finalized row's ledger data is already booked.
3. **Per-user, admins all.** Browse lists inherit it through `first_receipt` (the `OwnedByUser`
   scope). `CentralReg` is a **plain** model (no scope) so it can be resolved as a relation; the
   block screens scope `central_reg` **manually** by `user_id` (a global scope must never sit on a
   guarded update — see [[row-level-ownership-rule]]).
4. **CR receipt no via a relation.** `FirstReceipt->centralRegs` (hasMany — drafts can be
   double-booked); `primaryCentralReg()` picks the earliest for display.

## Verification

- Tests: `EntryCrTest` (generate: auto/attach/invalid/validation), `CrEntriesTest` (modes, CR No,
  status filter, per-user), `BlockCrTest` (block/unblock/edit-reason, required reason, owner guard).
- Whole suite **226 passing**. Smoke-tested on Postgres: 626 blocked / 358,020 clear rows resolve
  correctly with their reasons and DDO.

## Future

- A "blocked" count badge on the sidebar (skipped for now — not a pending worklist).
- Validate the `NOT VALID` FK once the 103k unlinked legacy `central_reg` rows are triaged.
