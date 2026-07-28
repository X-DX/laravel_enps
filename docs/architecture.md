# Modern eNPS — Architecture Design

*Status: **Approved** 2026-06-28 · Applies to: `enps-laravel/` (Laravel 13 / PHP 8.4 / PostgreSQL 17)*

This document is the agreed architecture for the modern eNPS. It describes **design only**.
Implementation proceeds module by module per the [Migration Roadmap](migration-roadmap.md).

## Approved decisions

| # | Decision | Choice |
|---|----------|--------|
| 1 | Coexistence model | **Cutover** — build the new app, then retire the legacy CI app. Schema may evolve per module *after* cutover. |
| 2 | Authorization | **Custom** roles/permissions enforced via Gates & Policies (no external RBAC package). |
| 3 | Initial schema strategy | **Compatibility-first (Phase A)** — Eloquent maps onto the existing migrated tables; no schema restructuring until cutover. |

---

## 0. Guiding principles

1. **Hard constraint:** the new app runs on the **already-migrated production data**; the
   **59 existing users must keep logging in**. No fresh/empty database.
2. **Clean architecture & learning:** clean layering, modern security, and *understood*
   decisions (this is a learning project).
3. **Close the analysis findings:** the design must visibly fix the critical issues in
   [legacy docs/08-analysis-report](../../legacy-enps-CI/docs/08-analysis-report.md).

---

## 1. Foundational strategy — Strangler Fig, compatibility-first

The new app grows *around* the existing data and replaces the legacy app **module by
module** (Strangler Fig). The schema is handled in two phases:

- **Phase A — Compatibility (during the build):** Eloquent models map onto the **existing
  tables as-is**. We modernize the *code* over the *current* data. Old users and data work
  immediately. No table restructuring.
- **Phase B — Schema evolution (after cutover):** once the legacy app is retired, introduce
  clean tables, foreign keys, correct types, and migrate data into them.

**Why not big-bang redesign + ETL now:** transforming 4.2M rows up front is high-risk,
passwords can't be re-hashed without plaintext, and it delays working software. Compatibility
-first delivers value immediately and defers risk to when it's safe (post-cutover).

---

## 2. Technology stack

| Concern | Choice | Why |
|--------|--------|-----|
| Framework / language | Laravel 13 / PHP 8.4 | Maintained; replaces EOL CI3/PHP7.4 |
| Database | PostgreSQL 17 | Already migrated; real constraints & types |
| UI | Livewire 4 + Alpine.js + Tailwind | Server-driven reactivity replaces jQuery + hand-built DataTables HTML |
| Auth | Laravel Auth + custom hasher | Native sessions/CSRF; bridges legacy SHA-256 (§6) |
| Authorization | Gates + Policies over custom RBAC schema | Decision #2; teaches the concept |
| Validation | Form Requests (+ Livewire rules) | Centralized, testable; removes inline `xss_clean` |
| Background work | Database queue + Jobs | Heavy exports/reports off the request cycle |
| Excel / PDF | Laravel Excel (PhpSpreadsheet) / DomPDF | Replaces abandoned PHPExcel |
| Audit | `spatie/laravel-activitylog` | Audit trail for a financial system |
| Testing | Pest (PHPUnit) | Feature + unit tests (legacy has none) |

External packages introduced: `maatwebsite/excel`, `barryvdh/laravel-dompdf`,
`spatie/laravel-activitylog`. Everything else is Laravel-native.

---

## 3. Application architecture — where logic lives

Fixes the legacy "fat controller" disease by layering responsibilities:

```
Request
  → Route → Middleware (auth, permission, throttle)
  → Livewire Component / thin Controller   (orchestration only; validation via Form Request)
  → Service class                          (business workflow + DB transaction)
  → Eloquent Models                        (data + relationships + query scopes)
  → PostgreSQL
       ↑ side-effects via Events→Listeners; heavy work via Jobs
```

- **Service layer (yes):** one class per business workflow (issue account, generate CR, post
  contribution, compute interest, export), each owning its transaction. Reusable from
  Livewire, controllers, jobs, and tests; kills duplication; makes money logic unit-testable.
- **Repository pattern (mostly no):** Eloquent already is the query layer. Use a thin
  repository only for genuinely complex legacy queries (e.g. balance-sheet roll-ups).
- **Form Requests:** centralize validation; remove deprecated `xss_clean`.
- **Events/Listeners:** login history, activity-log, notifications — keep services focused.
- **Jobs + database queue:** exports, bulk interest runs, letter/PDF generation.
- **Enums & value objects:** replace magic strings (`save_flag`, status, pension type) and
  wrap money/account-number logic.

---

## 4. Domain modules & build order

| Order | Module | Replaces (legacy) |
|------:|--------|-------------------|
| 1 | Auth & Access | `Auth`, `User`, `ValidateMenu` |
| 2 | Authorization (RBAC) | `menu_items`, `menu_ids` CSV |
| 3 | Master Data | Department, DDO, Bank, Designation, District, Location, rates |
| 4 | Subscriber & Account | `IssueAccount`, `allotment_accnt_no` |
| 5 | PRAN & Letters | `pran_no`, `Letter` |
| 6 | Money Intake | `FirstEntry`, `CentralRegister` |
| 7 | Contributions | `Employee`, `Arrear`, `MissingCredit`, `corpus_register` |
| 8 | Settlement | `Interest`, `Balance`, balance sheets |
| 9 | Reports & Exports | ReportSection, `ExportData` |
| 10 | Notifications | (new) |

Full detail, testability, complexity, and risk per milestone are in the
[Migration Roadmap](migration-roadmap.md).

---

## 5. Data architecture

**Phase A (now):** models map to existing tables with the overrides the legacy schema forces
(string non-incrementing PKs, no timestamps, no `hashed` cast on SHA-256 passwords). **Query
scopes** hide the ugliness (e.g. a `pending()` scope instead of `where('save_flag','T')`).
**New, additive tables are allowed** in Phase A when they don't touch legacy tables — e.g.
the RBAC tables (§7).

**Phase B (post-cutover) principles:**
- **Real foreign keys** for every relationship the legacy left loose.
- **Money as `NUMERIC(14,2)`**, never `double`/`float` — float silently loses paise.
- **Enums** for states (`pension_type`, workflow flags, closure reason).
- **Proper UTF-8** and uniformly transactional storage (fixes latin1 + MyISAM).
- **Surrogate `bigint` PKs**, keeping `account_no`/`pran` as unique business keys.
- **Drop the 5 `employee_reg_*` snapshot tables**; replace with audit log + backups.
- **Timestamps + soft deletes** where deletes must be reversible (financial ledger).

---

## 6. Authentication design

Laravel session auth with a **custom password hasher** understanding both schemes.

| Legacy | New design | Why |
|--------|-----------|-----|
| Unsalted SHA-256 | Custom hasher: verify SHA-256 (legacy) or bcrypt (new); `needsRehash()=true` for SHA-256 → **auto-upgrade to bcrypt on successful login** | Old users keep working *and* silently migrate; zero resets |
| Browser JS salt-challenge | Drop; submit over HTTPS, verify server-side | Challenge added complexity, not at-rest strength |
| CSRF disabled | Laravel CSRF on by default | Closes biggest hole |
| Session-based rate limit | Server-side `RateLimiter` (username+IP) | Real throttling |
| Insecure cookies | `secure` + `httponly` + `SameSite`, encrypted sessions | Closes XSS→session-theft |
| CAPTCHA, IP-lock, 30-day expiry, first-login change | Kept, re-implemented cleanly (middleware + `password_changed_at`) | Preserve operator-relied behaviors |

Pieces: a Form Request, an `AuthenticationService` (status/IP/expiry/first-login gates),
Events (login-history, activity-log), and middleware (throttle/expiry).

---

## 7. Authorization design (custom)

Legacy authorization is **per-user** menu IDs (CSV). The new model keeps that flexibility,
first-class:

- `roles` — Admin / Staff / User (from `role_flag`)
- `permissions` — one per action/screen (derived from `menu_items`)
- `role_permission` — defaults per role
- `user_permission` — per-user grants/overrides (preserves legacy per-user behavior)
- `menu_items` — becomes **navigation** metadata, each linked to a permission

These are **new additive tables** (safe in Phase A). Enforcement via **Gates + Policies**,
`can:` route middleware, and `@can` in Blade/Livewire. Admin bypass via a Gate `before` hook.

---

## 8. Reporting, jobs & exports

- **On-screen lists:** Livewire with **server-side pagination** + filters (replaces DataTables AJAX).
- **Excel/PDF:** Laravel Excel + DomPDF, generated in **queued Jobs**, offered as download.
- **CRA exports:** a `CraExportService` + Job; export state (`batch_id`, `export_count`, timestamps) modeled explicitly.

---

## 9. How the design closes the analysis findings

| Finding | Addressed by |
|---------|--------------|
| 4.1 CSRF off | Laravel CSRF on |
| 4.2 hard-coded creds | `.env` + config |
| 4.3 SHA-256 | Custom hasher + rehash-on-login |
| 4.4/4.6 cookies/key | Secure/httponly/encrypted sessions |
| 4.5 manual XSS | Blade auto-escaping + Form Requests |
| 4.7 SQL interpolation | Eloquent binding only |
| 4.9 session throttle | Server-side RateLimiter |
| 5.1 unindexed scans | Indexes + paginated queries |
| 2.1/2.2/2.3 EOL stack | Laravel 13 / PHP 8.4 / PG17 / PhpSpreadsheet |
| 6.2 fat controllers | Service layer |
| 6.1 no tests | Pest feature/unit tests |
| 1.2/1.3 dup tables/no FKs | Phase B clean schema |

---

## 10. Performance design

- **Index the hot predicates** the legacy never did: composite indexes on `(user_id, status,
  date)` and `(account_no, fin_year)`; Postgres **partial indexes** for "pending" rows.
- **Always paginate** at the DB; never fetch-all-and-loop.
- **Eager loading** to kill N+1; consistent via query scopes.
- **Cache reference data** (departments, banks, DDOs, rates) — small, hot, rarely changes.

---

## 11. Testing strategy

- **Unit tests** for money-critical logic: contribution split (10/14), account-number
  generation, interest calculation, the legacy-vs-bcrypt hasher.
- **Feature tests** for each workflow: login (incl. legacy-hash upgrade), permission
  enforcement, posting a contribution, closing an account.
- Goal: every business rule in the legacy Business-Rules doc has a test.

---

## 12. Proposed folder structure (target)

```
app/
├── Models/                  Eloquent models (mapped to data)
├── Services/                business workflows, by domain
│   ├── Auth/  Subscriber/  Contribution/  Settlement/  Export/
├── Livewire/                UI components, by domain
├── Http/
│   ├── Requests/            Form Requests (validation)
│   └── Middleware/          role/permission, password-expiry, IP-lock
├── Policies/                authorization
├── Jobs/                    queued exports/reports/letters
├── Events/ Listeners/       login history, activity log, notifications
├── Enums/                   pension type, workflow status, roles
└── Support/                 value objects (Money, AccountNumber)
```
