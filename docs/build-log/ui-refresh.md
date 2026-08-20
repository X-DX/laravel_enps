# UI Refresh — icons, collapsible sidebar, analytics dashboard

A visual/UX pass across the authenticated shell — outside the milestone sequence.

## What changed

**Icon system** — a self-contained `<x-icon name="…" />` component ([icon.blade.php](../../resources/views/components/icon.blade.php)) holding ~35 inline Heroicons (no icon font, no external JS). `SidebarMenu` now emits an `icon` for every **section, sub-section, and item** — sections from a fixed map, everything else inferred from keywords (`iconFor()`), so new menu items get a sensible icon automatically.

**Collapsible sidebar** — a toggle collapses the desktop sidebar to an **icon rail** (click any rail icon to expand); the state is persisted to `localStorage`. On mobile it's an off-canvas **drawer** with a backdrop, opened from a header button. Alpine state (`collapsed`, `mobileOpen`) lives on the layout so the header toggle and the sidebar share it.

**Analytics dashboard** — `/dashboard` is now a Livewire `Dashboard` component instead of a static view. It shows real KPIs (subscribers, finalized, pending, PRANs, pending PRANs, closed), a **top-departments bar chart**, a **PRAN-coverage ring** (inline SVG), a **NPS/UPS pension split**, master-data counts, and permission-aware **quick actions**. Every count is guarded by `Schema::hasTable` so the page renders as zeros where a table is absent (keeps the auth tests green).

**Premium polish** — loads the **Sora** display font (`font-display`) alongside self-hosted Instrument Sans; a vibrant blurred-orb page backdrop; glass cards with hover-lift; gradient accents; and entrance animations (`animate-fade-in-up`, `animate-pop-in`, animated chart bars) added to `app.css`.

## Round 2 — interaction polish

- **Global progress bar** — a gradient bar at the top of the page during any Livewire request or `wire:navigate`, driven from `app.js` (hooks `Livewire.hook('request')` + the navigate events). Plus a `wire:loading` dim on the account table during search/pagination.
- **Command palette (⌘K / Ctrl+K)** — a Livewire `CommandPalette` rendered once in the layout. Fuzzy-jump to any permitted screen *or* live-search accounts by name/number and open them. Full keyboard nav (↑ ↓ ↵, Esc), a header search button, mouse hover selection. **Gotcha:** `@livewire:navigated` collides with Blade's `@livewire` directive — use `x-on:livewire:navigated` instead.
- **Live sidebar badges** — `SidebarMenu::badgeFor()` counts pending worklists (Pending Issue Accounts, Pending PRANs) and rolls them up to the sub-section, section, and collapsed icon-rail (a dot). Guarded for absent tables.
- **Count-up numbers** — an Alpine `countUp()` helper animates the dashboard KPIs from 0 (honours `prefers-reduced-motion`; falls back to the server-rendered number so there's no flash of zero).
- **Reusable `<x-empty-state>` and `<x-page-header>`** — polished empty states (icon + message) and a consistent header with breadcrumbs + an `actions` slot. Applied to the account list; ready to roll out to the rest.

## Notes / decisions

- **Robustness:** `User::permissionKeys()` now guards on `Schema::hasTable('permissions')` (same defensive pattern `SidebarMenu` already used) so permission-gated views render even where the RBAC tables are absent — this is why the `@can` blocks on the dashboard don't break the password-change auth test.
- **Assets:** run `npm run dev` (or `npm run build`) after pulling — the new Tailwind classes/animations/fonts must be compiled. `public/build` is gitignored.
- Full suite unchanged at **159 passing** (this was a view/asset change; no business logic touched).
