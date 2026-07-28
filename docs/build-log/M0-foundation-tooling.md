# M0 — Foundation & Tooling (Explained for Beginners)

> **What this milestone is, in one sentence:**
> Get an *empty but fully working* Laravel app talking to the migrated PostgreSQL
> database, with the frontend build pipeline and developer tools in place —
> **before** we write a single feature.

Think of M0 as *preparing the workshop*: buy the tools, plug them in, and make
sure the lights turn on. No furniture is built yet — that starts in M1.

---

## 1. The Laravel project itself

**What we did**

```bash
composer create-project laravel/laravel enps
```

**What this command means**

- `composer` is PHP's **package manager** (like `npm` for JavaScript). It downloads
  libraries your project depends on and keeps track of their versions in
  `composer.json` / `composer.lock`.
- `create-project laravel/laravel` tells Composer: *"download the official Laravel
  starter skeleton and set it up for me."*
- `enps` is the folder name for the new app.

**Why Laravel**

The whole modernization goal (see `CLAUDE.md`) is to move off CodeIgniter 3 onto a
modern, batteries-included framework. Laravel gives us routing, an ORM (Eloquent),
authentication, validation, queues, mail, and testing **out of the box**, so we
spend time on *business logic* instead of plumbing.

**Versions we're on** (from `composer.json`):

| Thing | Version | Why it matters |
|-------|---------|----------------|
| PHP | `^8.3` (running 8.4) | Modern language features (typed properties, enums, readonly). |
| `laravel/framework` | `^13.8` | The framework itself. |

---

## 2. The database — PostgreSQL 17 (already migrated)

The legacy data lived in MySQL/MariaDB. It was migrated to **PostgreSQL 17** using a
tool called **pgloader** (full write-up lives in the project `README.md`). Short
version of *why Postgres*:

- Stricter data integrity (it **rejects** invalid junk like MySQL's `0000-00-00`
  dates — which is exactly why we had to clean data during migration).
- Better support for real constraints, `NUMERIC` money types, JSON, and schemas.

**How Laravel connects to it** — the `.env` file (environment configuration; secrets
and per-machine settings live here, never in code):

```dotenv
DB_CONNECTION=pgsql      # use the PostgreSQL driver (not the default mysql)
DB_HOST=127.0.0.1
DB_PORT=5432             # Postgres' default port
DB_DATABASE=enps
DB_USERNAME=aruproy
DB_PASSWORD=
DB_SCHEMA=enps           # our tables live in the "enps" schema, not "public"
```

> **New concept — a "schema" in Postgres:** it's like a *namespace/folder* for
> tables inside one database. Our migrated tables sit in a schema called `enps`,
> so we tell Laravel about it with `DB_SCHEMA=enps`.

---

## 3. The frontend build pipeline — Vite + Tailwind + Livewire + Alpine

Modern frontends don't ship raw CSS/JS; a **bundler** compiles and optimises them.

**What we installed** (from `package.json` → run with `npm install`):

| Package | What it is | Why we need it |
|---------|-----------|----------------|
| `vite` | The **bundler/dev server**. Compiles CSS/JS, does hot-reload in dev, minifies for production. | Turns our source `resources/css/app.css` into the optimised `public/build/...` files the browser loads. |
| `laravel-vite-plugin` | Glue between Laravel and Vite. | Lets Blade use `@vite(...)` and know which built file to load. |
| `tailwindcss` `^4` | A **utility-first CSS framework** — you style with classes like `px-3 text-slate-500` instead of writing CSS files. | CLAUDE.md mandates Tailwind; it makes the "beautiful, modern" UI fast to build and consistent. |
| `@tailwindcss/vite` | The Tailwind v4 plugin for Vite. | Tailwind v4 is configured *inside CSS* (`@import "tailwindcss"`) and built by Vite — no separate `tailwind.config.js` needed. |
| `concurrently` | Runs several commands at once. | Lets `npm run dev` start Vite (and other watchers) together. |

**Livewire 4** (a PHP/Composer package, not npm):

```bash
composer require livewire/livewire
```

> **New concept — Livewire:** it lets you build **dynamic, reactive UIs writing
> only PHP + Blade** (no separate React/Vue app). You write a PHP class with public
> properties and methods; Livewire re-renders the HTML when they change, over AJAX,
> automatically. Our login form, captcha refresh, and admin screens are Livewire
> components. It **ships with Alpine.js** for small bits of in-browser interactivity
> (dropdowns, toggles), so we get Alpine "for free."

---

## 4. Developer tooling (quality-of-life + testing)

These come pre-installed with the Laravel skeleton (they live under `require-dev` in
`composer.json`, meaning they're only installed in development, never on production):

| Tool | What it does | Why it matters |
|------|--------------|----------------|
| `phpunit/phpunit` `^12` | The **test runner**. | Every slice we build is verified by automated tests. We use PHPUnit (not Pest). |
| `laravel/tinker` | An interactive **REPL** — a PHP shell with your app booted (`php artisan tinker`). | Lets us poke at models/data live, e.g. `User::count()`. |
| `laravel/pint` | Opinionated **code formatter** (PSR-12). | Keeps code style consistent (CLAUDE.md asks for PSR-12). |
| `laravel/pail` | **Live log viewer** (`php artisan pail`). | Tail application logs in a readable way during dev. |
| `nunomaduro/collision` | Pretty **error output** in the console/tests. | Readable stack traces. |
| `fakerphp/faker` | Generates **fake data** for factories/seeders. | Used when we need sample rows in tests. |
| `mockery/mockery` | **Mocking** library for tests. | Fake out dependencies in unit tests. |

---

## 5. Session / cache / queue decisions (important, and easy to trip on)

Laravel needs somewhere to store **sessions** (per-user login state between requests),
**cache**, and **queued jobs**. By default the skeleton points these at *database
tables* — but our `enps` schema is the migrated legacy DB and has **no**
`sessions` / `cache` / `jobs` tables. Pointing at missing tables = instant 500 error.

So for now (`.env`):

```dotenv
SESSION_DRIVER=file      # store sessions as files on disk (storage/framework/sessions)
CACHE_STORE=file         # store cache as files too
QUEUE_CONNECTION=database # declared, but not actually used until M5/M9
```

**Why `file`:** zero setup, no extra tables, perfect for local dev and a small
internal back-office. We *deliberately deferred* the "real" production choice
(file vs. Redis vs. adding Laravel's own tables) to later milestones — the jobs and
cache tables get created when **queues** actually arrive (around M5). This is why,
in M2, we **removed the three stock scaffold migrations** (`users`, `cache`,
`jobs`) — we don't use a `users` table (we use `user_account`), and cache/jobs tables
come later.

---

## 6. How to run the app day-to-day

```bash
# 1. Start the PHP application server (serves the app at http://127.0.0.1:8000)
php artisan serve

# 2a. During active UI work — Vite dev server with hot reload:
npm run dev

# 2b. OR, if you're not editing CSS/JS — build the assets once:
npm run build
```

> **`artisan`** is Laravel's command-line tool (`php artisan <command>`). You'll use
> it constantly: `migrate`, `tinker`, `test`, `route:list`, `make:model`, etc.

If you see stale styles or an old page, two commands fix 99% of it:

```bash
php artisan view:clear   # clear compiled Blade templates
npm run build            # rebuild CSS/JS (needed when NOT running `npm run dev`)
```

---

## 7. What we deliberately did NOT do in M0

- **No feature tables, no schema changes.** Compatibility-first: we map onto the
  migrated tables as-is (see `architecture.md`). New tables only appear when a
  milestone needs them, and only *additively*.
- **No `users` table.** Authentication targets the legacy `user_account` (that's M1).
- **No queue/cache tables yet.** Deferred until queues are actually used.

---

## ✅ M0 — Foundation: status

Scaffold created, PostgreSQL wired up (schema `enps`), Livewire 4 + Tailwind v4 + Vite
build pipeline working, dev tooling and testing in place, and the session/cache
strategy chosen for local dev. **The workshop is ready — M1 builds the first real
feature: authentication.**
