# Landing Page — Build Notes

*An extra, outside the milestone sequence — a public marketing/entry page at `/`.*

**What:** A premium public landing page with modern UI/UX — custom lerp **cursor** (ring + dot),
**mouse-parallax** floating objects, **scroll-reveal** animations, a persisted **dark/light toggle**
(no flash-of-wrong-theme), and the **Sign in** CTA.

**Why:** Requested before M2. `/` previously just redirected to the dashboard/login.

**Files:**
- `resources/views/landing.blade.php` — nav, hero, stats, features, security, CTA, footer
- `resources/views/components/layouts/marketing.blade.php` — shell: no-FOUC dark init, cursor, scoped JS
- `resources/css/app.css` — `@custom-variant dark`, keyframes, cursor/reveal/grid styles
- `routes/web.php` — `/` now renders `landing` (was a redirect to dashboard)
- `tests/Feature/LandingPageTest.php`

**Key decisions:**
- **Dark mode:** class-based via Tailwind v4 `@custom-variant dark (&:where(.dark, .dark *))`, toggled
  on `<html>`, persisted in localStorage; an inline `<head>` script applies it before paint (no flash).
  Scoped to the marketing layout — the existing app pages are unaffected.
- **No Alpine dependency:** all interactivity (cursor, parallax, reveal, toggle) is scoped **vanilla JS**
  in the marketing layout, so it never loads/runs on the Livewire app pages.
- **Parallax vs float conflict:** floating blobs use a nested structure — the outer element gets the JS
  parallax transform, the inner gets the CSS float keyframe — so the two transforms don't fight.
- **Domain-true content:** stats and the preview card reflect the real system (10%/14% split, ~50M
  records, 46 modules, ~3,000 DDOs) and showcase the M1 security work.
- **Public page:** `/` is accessible to everyone; the CTA swaps to "Dashboard" for authenticated users.

**Verification:** full suite 19 passed / 70 assertions; `/` → 200 with cursor/parallax/gradient/toggle
markup present; app.css rebuilt (~82 KB).

**Gotchas:**
- `@custom-variant dark` is required in Tailwind v4 for a *class*-based toggle (the default is the
  `prefers-color-scheme` media query).
- Custom cursor + parallax are gated behind `matchMedia('(pointer: fine)')`, so touch devices are unaffected.

## Update — dark mode extended to all pages

Extended the toggle to the **login, change-password, and dashboard** pages via two reusable Blade
components: `resources/views/components/theme/init.blade.php` (no-FOUC head script) and
`resources/views/components/theme/toggle.blade.php` (button with an inline handler, so it works on
Livewire *and* plain Blade pages). The `guest` and `app` layouts include both; the auth views were
restyled with `dark:` variants (light default + dark override), and the captcha image keeps a dark
box in both themes. Theme persists across every page via localStorage. Verified: 19 tests pass; the
login page renders the toggle + light/dark classes.
