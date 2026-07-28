# Modern eNPS — Migration Roadmap

*Status: Approved 2026-06-28 · Companion to [architecture.md](architecture.md)*

Strategy: **Strangler Fig, compatibility-first, cutover** (see architecture §1). Work is
broken into **independently testable milestones**. Each milestone has an exit test (its
definition of done), a **complexity** rating, and **risks**.

## How to read this

**Complexity scale** (relative effort & conceptual difficulty, *not* calendar time):

| Rating | Meaning |
|--------|---------|
| ◍ Low | Mostly mechanical; established patterns |
| ◍◍ Medium | Several moving parts; some new concepts |
| ◍◍◍ High | Concurrency / money / large data / external formats |
| ◍◍◍◍ Very High | Money-critical *and* large-scale *and* must reconcile with legacy |

**Risk levels:** 🟢 low · 🟡 medium · 🔴 high.

Each milestone is **shippable and testable on its own**, so we can stop, review, and learn
between them (per CLAUDE.md's module-by-module workflow).

---

## M0 — Foundation & Tooling  ◍ Low  🟢

Get a clean, verifiable base before any feature work.

- Clean Laravel 13 scaffold (done); DB wired to the `enps` schema via `search_path` (done).
- Dev quality gates: Pint (style), Larastan/PHPStan (static analysis), Pest (tests), a basic
  CI check.
- Base Tailwind/Livewire layout, error handling, logging, `.env` conventions.

**Exit test:** app boots, connects to the `enps` schema, `php artisan test` runs green, a
smoke test asserts the DB connection and one reference-table read.

**Risks:** 🟢 local PHP extensions / Postgres availability.

---

## M1 — Auth & Access  ◍◍◍ High  🔴

The foundation everything else sits behind, and security-critical.

- `User` model mapped to `user_account` (string PK, no timestamps, no `hashed` cast).
- **Custom hasher:** verify legacy SHA-256, verify bcrypt, and rehash SHA-256 → bcrypt on
  successful login.
- Login / logout, CSRF, secure & encrypted sessions.
- Re-implement legacy gates: CAPTCHA, **server-side** rate limiting, IP-lock, password
  expiry (30 days), forced first-login change.
- Login history via an event/listener.

**Exit test:** every one of the 59 migrated users logs in with their existing password; a
SHA-256 user's stored hash becomes bcrypt after first successful login; lockout triggers
after N failed attempts; disabled (`user_status=0`) users are blocked; IP mismatch is blocked;
expired/first-login users are forced to change password.

**Risks:** 🔴 hasher correctness (security-critical; needs thorough tests); session/cookie
config; faithfully reproducing the legacy gates without lockouts in production.

---

## M2 — Authorization (RBAC)  ◍◍ Medium  🟡

Replace the CSV `menu_ids` ACL with a real, custom permission model.

- New additive tables: `roles`, `permissions`, `role_permission`, `user_permission`;
  `menu_items` repurposed as navigation linked to permissions.
- Seed roles from `role_flag` and permissions/grants from each user's `menu_ids`.
- Gates + Policies + `can:` middleware; `@can` in the UI; admin bypass via Gate `before`.

**Exit test:** a staff user sees and can access only their permitted menus; a gate denies an
unpermitted action; admin bypasses all checks; a per-user override grants/revokes correctly;
**parity check** — for a sample of real users, the new permissions match what their legacy
`menu_ids` allowed.

**Risks:** 🟡 correctly translating the CSV → permissions (the source of truth for who can do
what); missing a permission locks real operators out.

---

## M3 — App Shell & Master Data  ◍◍ Medium  🟢

Establish the reusable CRUD pattern on low-risk data.

- Authenticated layout, role-based dashboards, permission-driven navigation.
- CRUD for: Department, District, Location, DDO, Bank, Designation, Purpose codes, Interest
  rate, DA rates, Closure reasons, Contribution share.

**Exit test:** each master entity supports list (paginated) / create / edit / delete against
its existing table; validation via Form Requests; authorization enforced; an N+1-free list.

**Risks:** 🟢 mostly volume; this milestone sets the patterns reused everywhere later.

---

## M4 — Subscriber & Account  ◍◍◍ High  🔴

- Subscriber create/edit on `allotment_accnt_no`; nominees; single-mother rule.
- **Account-number generation** `AP/{NPS|UPS}/{dept}/{seq}` via `account_sequence`.
- Close account (Death / VRS → `isActive=0`); finalize (`save_flag` T→F).

**Exit test:** account numbers generate correctly and **uniquely per department** under
concurrent requests; validations enforced (PRAN 12 digits, letters-only names, single-mother
waives father); closure sets the right flags/reason/date; finalize transitions state.

**Risks:** 🔴 **account-number race conditions** (the legacy per-dept counter is not
concurrency-safe); data-entry validation parity.

---

## M5 — PRAN & Letters  ◍◍ Medium  🟡

- PRAN allotment on `pran_no`; duplicate-PRAN checks (per account, global).
- Allotment letters (`letter_generate`) as **DomPDF**, generated via a queued Job.

**Exit test:** a PRAN cannot be reused across accounts; letter PDF generates and matches the
legacy layout for a sample; `letter_generated_flag`/date set correctly.

**Risks:** 🟡 PDF layout parity with the legacy letter format.

---

## M6 — Money Intake (First Receipt → Central Register)  ◍◍◍ High  🔴

- `first_receipt` entry; CR generation incrementing `counter_centralreg`
  (`centregno`/`recept_no`) atomically; block/unblock CR.
- Flag lifecycle `T → CR → FZ`, all in DB transactions.

**Exit test:** receipt entry works; CR finalize increments both counters **atomically and
safely under concurrency**; flags transition correctly; block/unblock works; amounts
reconcile against the originating receipts.

**Risks:** 🔴 **counter concurrency** (manual sequence is race-prone); transactional integrity
of money records.

---

## M7 — Contributions (the core ledger)  ◍◍◍◍ Very High  🔴

The heart of the system — money-critical and on the ~53M-row table.

- Posting to `employee_reg` (employee 10% + government 14% + total + pay).
- `arrear_reg` (back-dated), `missing_crdt_adjustment` (corrections), `corpus_register`
  (UPS, DA-based corpus share).
- Post → finalize → export-ready; **add indexes** on the hot predicates.

**Exit test:** posting math matches the rules exactly; the unique key blocks double-posting a
month; arrears and missing credits post and reconcile; UPS corpus uses the correct DA rate;
list screens are paginated and fast (indexed); a reconciliation report matches legacy totals
for sample accounts.

**Risks:** 🔴 money-math correctness; **performance** on a huge table; concurrency; reconciling
exactly with legacy values (float vs decimal differences must be understood).

---

## M8 — Settlement (Interest & Balances)  ◍◍◍◍ Very High  🔴

- Interest rate per `fin_year`; account-level interest application.
- Balance roll-ups (`balance_sheet`, arrear, missing): `open + contributions + interest =
  close`, carried forward year to year.

**Exit test:** interest computed per year matches the configured `rate`; balance roll-up and
carry-forward are correct across multiple years; **reconciliation** — computed closing
balances match legacy balances for a sample of real accounts within an agreed tolerance.

**Risks:** 🔴 **calculation parity** with the legacy system (the legacy used floats and
`decimal(10,0)`; rounding differences must be reconciled and explained, not hidden).

---

## M9 — Reports & Exports  ◍◍◍ High  🟡

- Report screens (contribution, subscriber, audit, transit-bank).
- Excel (Laravel Excel) + PDF (DomPDF); **CRA export** (`batch_id`, `export_count`,
  timestamps) as queued Jobs with downloads.

**Exit test:** report outputs match legacy for sample data; the CRA export file format is
byte-valid for the downstream system; queued generation produces a correct, downloadable file;
re-export increments counters correctly.

**Risks:** 🔴 **CRA file-format exactness** (external dependency — getting a column or width
wrong breaks the national upload); report parity.

---

## M10 — Notifications  ◍◍ Medium  🟢

- Mail/notifications: password reset, letter dispatch, operational alerts.

**Exit test:** the right notification fires on its event; mailables render; failures are
logged and retried via the queue.

**Risks:** 🟢 mail configuration; low domain risk.

---

## M11 — Cutover & Hardening  ◍◍◍ High  🔴

The go-live milestone — switch off the legacy app.

- Full cross-module **reconciliation** vs legacy; UAT with real operators.
- Load/performance testing on production-scale data; security review (re-check every Doc 08
  finding is closed); final index/constraint tuning.
- Cutover plan + rollback plan; retire CI.

**Exit test:** reconciliation reports clean across modules; UAT sign-off; load test meets
targets; security checklist complete; rollback rehearsed.

**Risks:** 🔴 data parity, user acceptance, and go-live coordination; a financial system
cannot lose or mis-state a single contribution.

---

## M12 — Phase B: Schema Evolution (post-cutover)  ◍◍◍ High  🔴

Only **after** CI is retired and the schema is no longer shared.

- Introduce clean tables (FKs, `NUMERIC` money, enums, UTF-8), drop the 5 snapshot tables,
  migrate data into the normalized schema.
- Tighten models to the new schema; keep all tests green.

**Exit test:** migrated data reconciles 1:1 with pre-migration totals; constraints enforced;
full test suite passes on the new schema.

**Risks:** 🔴 large one-time data migration; must be reversible and fully reconciled.

---

## Global risk register (top cross-cutting risks)

| Risk | Where | Mitigation |
|------|-------|-----------|
| 🔴 Money-math / balance **parity with legacy** | M7, M8 | Reconciliation tests against real sample accounts; document every rounding rule |
| 🔴 **Counter concurrency** (account no, CR no) | M4, M6 | Use DB-level locking/sequences; concurrency tests |
| 🔴 **Performance** on the 53M-row ledger | M7 | Add indexes first; always paginate; load test |
| 🔴 **CRA export format** correctness | M9 | Golden-file tests vs a known-good legacy export |
| 🔴 **Auth hasher** correctness | M1 | Exhaustive unit tests; verify all 59 users before cutover |
| 🟡 **Frozen schema** limits during build | M1–M11 | Only additive tables in Phase A; defer restructuring to M12 |
| 🟡 **Permission translation** from `menu_ids` | M2 | Parity check per real user; manual review of admin grants |
| 🟡 **Subscriber/letter/report parity** | M5, M9 | Golden-file comparisons with legacy output |

---

## Dependencies between milestones

```
M0 → M1 → M2 → M3 → M4 → M5
                     └→ M6 → M7 → M8 → M9 → M11 → M12
M10 can proceed in parallel once M1 (events) exists.
```

M1 and M2 gate everything (auth + access). M7 depends on M4/M6 (accounts + CR). M8 depends on
M7 (contributions). M11 depends on all feature modules. M12 is strictly post-cutover.
