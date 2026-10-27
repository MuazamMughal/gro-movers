# Plan: public site from Blade to Vue 3 (Inertia SSR)

> **Status: deferred (2026-10-27).** The team lead decided to keep the Blade public site for now. This plan is kept for a later switch; nothing in it has been implemented. The admin panel is already Vue 3 (Inertia).

**Proposal:** the public website moves from Blade to **Vue 3 + TypeScript + Tailwind 4**, rendered with **Inertia SSR** (Node) and still saved as static HTML. Production cPanel uses **Setup Node.js App** for the SSR process. This replaces the "Blade public site" decision in `01-requirements-and-feasibility.md` §2 and `02-build-prompt.md` §2.

**The current frontend is final.** The Vue version must look and behave the same at 375, 768 and 1280 px: same markup structure, classes, copy, links, schema and SEO output. This is a port, not a redesign.

---

## 1. Architecture

| Piece | Now | After |
|---|---|---|
| Public pages | `resources/views/**/*.blade.php` | Inertia pages in `resources/js/site/pages/**/*.vue` |
| Root template | `layouts/site.blade.php` | `resources/views/site.blade.php`: `<head>` only (SEO tags, JSON-LD, favicon, fonts, theme vars, integrations) printed **from page props by PHP**, then `@inertia` |
| Sections | `resources/views/sections/{type}.blade.php` | `resources/js/site/sections/{Type}.vue`, picked by a registry keyed by `type` |
| Components | `components/site/*.blade.php` | `resources/js/site/components/*.vue` (Header, Footer, TopBar, MobileBar, Breadcrumbs, Button, CallButton, Icon, Picture, QuoteForm, ContactForm, FormGuard, ReviewCard, PostCard, Pagination, Prose, InlineCta, Stars…) |
| Islands (`resources/js/site/islands`, `lib`) | Enhance static HTML | Logic moves into the Vue components (validation, phone mask, Places, Turnstile, analytics). `lib/*.ts` stays as plain modules. |
| Inertia apps | Admin only (`app.blade.php`) | Two apps: admin (unchanged) and site (`resources/js/site/app.ts` + `resources/js/site/ssr.ts`), separate Vite entries, separate root views and middleware |
| Static cache | HTML from Blade | HTML from SSR (same publisher, same `.htaccess`) |

**Rules**
- **SEO stays server-side.** `<title>`, meta description, canonical, robots, Open Graph, JSON-LD and the Search Console tag are printed by the Blade root template from props, so they are correct even if SSR fails. Vue renders only `<body>` content.
- **No-JS still works.** SSR output contains real `<form method="post" action="/forms/…">` forms with the honeypot and signed `_started` field; plain POST, the 422 refill page (`forms/invalid` → Inertia page) and `?sent=` thank-you event keep working.
- **Rich HTML** (sanitized server-side: post content with TOC, rich text, custom HTML) is passed as strings and rendered with `v-html`. Sanitizing stays in PHP.
- **Images:** serialize media to `{src, srcset per format, width, height, alt}` once in PHP (one `MediaData` transformer) and render with a `Picture.vue` that outputs the same `<picture>` markup as `components/picture`.
- **Icons:** use `lucide-vue-next` (already used by the admin) with the same allow-list and classes.
- **Tokens** (`{company}`, `{city}`…) are replaced in PHP before props are sent.
- **Links:** `QuoteLink::resolve()`, `Menus`, `Breadcrumbs` and `Contact` keep producing the data; Vue only renders it. Plain `<a href>` (no Inertia `<Link>`) on the public site, so every page is a full static page load.
- **Stay in Blade:** emails (`mail/*`), `errors/minimal-site` (500/503 must work with the DB or Node down), the admin preview error page, `site/placeholder` may become Vue.

## 2. Server side

1. `HandleSiteInertiaRequests` middleware for public routes: `rootView = 'site'`, shared props: company/contact info, menus (header, footer 1–3, mobile), theme flags, integrations needed client-side (Places key, Turnstile site key, analytics IDs), `quoteHref`.
2. Each site controller (`Site\PageController`, `ServiceController`, `AreaController`, `BlogController`, `ReviewController`, `FormController` invalid page, 404 handler, `SeoFileController` untouched) returns `Inertia::render('…', $props)` with the same data its Blade view used. Section data is serialized through one `SectionData` transformer (per type), reusing `SectionRenderer`/`AutoSections` logic.
3. SSR config: `config/inertia.php` ssr enabled for the site app with the bundle `bootstrap/ssr/ssr.mjs`; `php artisan inertia:start-ssr` runs it. Admin stays client-rendered.
4. **Static publisher safety:** if SSR is unreachable (Inertia falls back to client rendering), `StaticPublisher::build()` must **not** overwrite the existing file: detect the missing SSR body (e.g. empty `#app`/no `data-server-rendered` marker) and return `failed`, keeping the previous copy (same rule as 5xx).
5. `deploy:check`: add "SSR server reachable" (required in production) and "SSR bundle built".
6. `scripts/package-release.sh`: build both client and SSR bundles; include `bootstrap/ssr`.
7. Sail: add an `ssr` service running `php artisan inertia:start-ssr` (like `queue`), so dev and the static build use SSR.

## 3. Front end

- `resources/js/site/app.ts`: `createInertiaApp` with `createSSRApp` hydration, pages from `./pages/**/*.vue`, no progress bar, no admin plugins.
- `resources/js/site/ssr.ts`: `createServer` for SSR.
- `resources/css/site.css` stays the stylesheet (Tailwind `@source` must include `resources/js/site/**/*.vue`).
- Keep bundle small: no shadcn on the public site; only Vue, Inertia and lucide icons used.
- Critical CSS inlining, font preloads and relative asset URLs keep working on the SSR HTML (verify in the static output).

## 4. Tests and verification (definition of done)

1. **Visual parity:** before any change, capture full-page screenshots of every public URL (`SiteIndex`) at 375, 768 and 1280 px from the current static site (Playwright, headless) into `storage/app/parity/before`. After the port, capture `after` and diff (pixelmatch). Every page must match (allowing anti-aliasing noise). List any differences and fix them.
2. **HTML parity:** for each URL compare the old and new static HTML for: one H1, title/description/canonical/robots, JSON-LD graph, every link `href`, every form field name, image `srcset`s. No regressions.
3. **PHP tests:** all existing tests pass (currently 344 passing, 4 skipped). HTML assertions on public pages become `assertInertia` prop assertions, plus SSR-backed checks where markup matters (run with the SSR server up; skip cleanly with a message when it is down).
4. **Builds and checks:** `npm run build` (client + SSR), `vue-tsc --noEmit`, Pint, `php artisan static:build --now` builds all pages from SSR, no console errors and no hydration warnings in the browser.
5. **Behaviour:** quote and contact forms submit with and without JS (lead saved, Mailpit emails), Places suggestions, Turnstile with Cloudflare test keys, mobile menu, dropdowns, review slider, FAQ accordion, `#quote-form` links, GA events (`window.dataLayer`).
6. **Performance:** Lighthouse mobile on home, a service and a city page: Performance ≥ 90, Accessibility, Best Practices and SEO 100.
7. **Docs:** update `docs/deploy-cpanel.md` (Node.js App setup for SSR, update steps, troubleshooting), README, and the spec notes.

## 5. Order of work

1. Baseline screenshots and HTML snapshots (before touching code).
2. Site Inertia app, root view, middleware, SSR entry, Sail `ssr` service.
3. Shared components and layout (header, footer, top bar, mobile bar, breadcrumbs).
4. Sections (all 17 types), then page templates: home/page, services index/show, areas index/show, area × service, blog list/show/category, reviews, contact, quote, thank-you, legal, 404, forms/invalid, placeholder.
5. Forms with all islands logic.
6. Publisher safety, `deploy:check`, package script, docs.
7. Delete the replaced Blade views and island code; update tests.
8. Full verification (section 4), fix, repeat until clean.
