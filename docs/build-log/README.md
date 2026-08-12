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
| M4 — Subscriber & Account | [4a list](M4-4a-subscriber-list.md) · [4b detail](M4-4b-subscriber-detail.md) · [4c issue](M4-4c-issue-account.md) · [4d finalize](M4-4d-finalize-account.md) · [4e register screens](M4-4e-account-register-screens.md) | 🟡 in progress (4a–4e: list · detail · issue form · finalize · the 3 Account Register screens from one mode-driven component, Pending action bar = Excel·PDF·Finalize·Delete — 136 tests) |
| M5 — PRAN & Letters | _pending_ | ⬜ |
| M6 — Money Intake | _pending_ | ⬜ |
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

## Conventions

- ✅ done · 🟡 in progress · ⬜ not started
- Each slice is independently testable; verification commands/results are recorded.
- "Gotchas" capture non-obvious things worth remembering.
