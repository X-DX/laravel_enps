# Build Log

A running development journal. **One file per milestone**, with **notes per slice**.
Each slice records: *What*, *Why*, *Files*, *Key decisions*, *Verification*, and *Gotchas*.

This is the "why we did it this way" memory of the rebuild — read alongside
[architecture.md](../architecture.md) and [migration-roadmap.md](../migration-roadmap.md).

## Index

| Milestone | File | Status |
|-----------|------|--------|
| M0 — Foundation & Tooling | [M0-foundation-tooling.md](M0-foundation-tooling.md) | ✅ complete (scaffold, DB wiring, build pipeline, tooling) |
| M1 — Auth & Access | [M1-auth-access.md](M1-auth-access.md) | ✅ complete (1a–1e, 18 tests) |
| M2 — Authorization (RBAC) | [M2-authorization.md](M2-authorization.md) | ✅ complete (2a–2e, 36 tests) |
| M3 — Master Data | [M3-master-data.md](M3-master-data.md) | ✅ complete (3a–3e: District, Bank, Designation, Location, DDO, Settings — 89 tests) |
| M3 cleanup — `WithCrudTable` trait | [M3-refactor-crud-trait.md](M3-refactor-crud-trait.md) | ✅ refactor (5 screens de-duplicated; 89 tests unchanged) |
| M3 add-on — State in District Master | [M3-state-master.md](M3-state-master.md) | ✅ new `state_master` + `dist_master.state_code` (progressive backfill; 90 tests) |
| M3 add-on — Treasury Master | [M3-treasury-master.md](M3-treasury-master.md) | ✅ net-new `treasury_master` screen; first data-driven sidebar item added from scratch; digit-string PK preserves leading zeros (100 tests) |
| M3 change — DDO Master: Location → Treasury | [M3-ddo-treasury-swap.md](M3-ddo-treasury-swap.md) | ✅ additive `ddo_master.treasury_code` (nullable+FK); DDO re-pointed to Treasury; `loc_code` kept; progressive backfill (101 tests) |
| M3 change — DDO Master: real DDO Code | [M3-ddo-code-restructure.md](M3-ddo-code-restructure.md) | ✅ rename serial `ddo_code`→`ddo_sl`; new 7-digit `ddo_code` unique per treasury; hand-typed one concept at a time (104 tests) |
| M4 — Subscriber & Account | [4a list](M4-4a-subscriber-list.md) · [4b detail](M4-4b-subscriber-detail.md) · [4c issue](M4-4c-issue-account.md) · [4d finalize](M4-4d-finalize-account.md) · [4e register screens](M4-4e-account-register-screens.md) · [4f close](M4-4f-close-account.md) · [4g edit](M4-4g-edit-account.md) | ✅ complete (4a–4g: list · detail · issue · finalize · 3 register screens · close (dedicated `account_closure` register + guarded one-txn close) · per-row edit reusing the issue form with finalized-field freeze — 146 tests) |
| M5 — PRAN | [5.1 assign PRAN](M5-5.1-assign-pran.md) | ✅ complete (5.1 Assign PRAN: search account → add/update PRAN draft (12-digit, confirm-match, globally unique) → pending list with select-all + Finalize/Delete/Excel/PDF — 159 tests). **Part 2 (Letters) dropped** — business decision 2026-08-16; the 3 legacy letter menu items are hidden (permissions kept for a possible future revival) |
| M6 — First Register | [M6 first register](M6-first-register.md) | ✅ 6.1 complete (entry form: Treasury→DDO cascade, duplicate "save anyway", Draft→Double; View All / Pending / Finalized lists; Pending finalize/delete + per-row edit — 177 tests) |
| M7 — Contributions | _pending_ | ⬜ |
| M8 — Settlement | _pending_ | ⬜ |
| M9 — Reports & Exports | _pending_ | ⬜ |
| M10 — Notifications | _pending_ | ⬜ |
| M11 — Cutover & Hardening | _pending_ | ⬜ |
| M12 — Schema Evolution | _pending_ | ⬜ |

## Extras (outside the milestone sequence)

| Item | File | Status |
|------|------|--------|
| Landing page (`/`) | [landing-page.md](landing-page.md) | ✅ public entry page with dark mode, parallax, custom cursor |
| UI refresh (icons · sidebar · dashboard) | [ui-refresh.md](ui-refresh.md) | ✅ icon system, collapsible icon-rail sidebar + mobile drawer, Livewire analytics dashboard, premium fonts/animations; then command palette, progress bar, live badges, breadcrumbs everywhere |
| Migration to UPS (menu 161) | [migrate-to-ups.md](migrate-to-ups.md) | ✅ restored missing feature — data migration adds the menu item + permission; Livewire search → migrate NPS→UPS (guarded, transactional update + `ups_migration` log) — 164 tests |
| Row-level ownership (per-user data) | [row-level-ownership.md](row-level-ownership.md) | ✅ cross-cutting — `OwnedByUser` trait + global scope on `Subscriber`/`FirstReceipt`; per-user lists/detail/export/badges/dashboard, admins see all; route-model binding 404s non-owners — 185 tests |

## Conventions

- ✅ done · 🟡 in progress · ⬜ not started
- Each slice is independently testable; verification commands/results are recorded.
- "Gotchas" capture non-obvious things worth remembering.
