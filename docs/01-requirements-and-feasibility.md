# USA Moving Company Website: Feasibility, Requirements & Deliverables

**Status:** v1.0 FINAL (2026-10-27). All open questions are answered (see §10). The build spec is in `02-build-prompt.md`.
**Stack requested:** PHP + Vue.js + MySQL. The public site must be static or pre-rendered for SEO, and the admin panel must control everything.
**Reference site:** https://www.movinglead.co

---

## 1. Feasibility Summary

| Requirement | Feasible? | Notes |
|---|---|---|
| PHP + Vue.js + MySQL | ✅ Yes | Laravel (PHP) for the backend and public HTML, Vue 3 for the admin panel and interactive widgets. |
| Static or pre-rendered pages for SEO | ✅ Yes | See §2. A pure Vue SPA is **not** acceptable for SEO, so the public pages are rendered on the server and cached as static HTML. |
| Every heading and content block editable from admin | ✅ Yes | Section-based page builder, described in §4.3. |
| Dynamic Services, Areas and Blog (unlimited entries) | ✅ Yes | One template per type. The admin adds, removes and reorders entries. |
| Image cropper with fixed sizes | ✅ Yes | Vue cropper (e.g. `vue-advanced-cropper`). The server builds the sizes plus WebP versions. |
| Media stored on our own server | ✅ Yes | Laravel `public` disk, under `storage/app/public/media`. |
| Quote form leads stored and emailed | ✅ Yes | Saved in MySQL, emailed to the admin, with a lead inbox in the admin panel. |
| Address autocomplete for Moving From / Moving To | ✅ Yes | Google Places Autocomplete, limited to the US. The API key is set in the admin Settings. Without a key, the fields fall back to plain text. |
| cPanel hosting | ✅ Yes | Option A needs no Node on the server. Assets are built locally and uploaded. Queues use the `database` driver and run from cPanel cron. Images use GD, because Imagick is often missing on cPanel. Static files are served through `.htaccess` rewrites. |
| Same look as the reference site | ⚠️ Partly | movinglead.co sells **leads to movers**. It is not a moving company. We copy its **layout style** (hero, trust bar, steps, FAQ, coverage grid, sticky CTA) and not its content or branding. |

### Local environment check (this Mac)
- Node v24 ✅ and **Docker 28.5 / Docker Desktop** ✅ are installed.
- PHP, Composer and MySQL are not installed, and **they are not needed**. ✅ **DECIDED:** development runs in **Laravel Sail (Docker)** with PHP 8.3, MySQL 8.0, Mailpit, a queue worker, a scheduler, and an optional Apache container that tests the `.htaccess` rules. Production stays cPanel. See build prompt §1.1.

---

## 2. Architecture Decision: How We Get "Static" Pages

### Option A (RECOMMENDED): Laravel SSR + Static HTML Cache
- **Public site:** Laravel Blade renders complete HTML on the server. Google sees all the content with no JS required.
- **Static cache:** On every **Publish** in the admin, the system regenerates the HTML file for each affected page into `public/static/...`. Nginx or Apache serves that file directly, with no PHP and no DB hit, so pages load in milliseconds.
- **Vue on the public site:** Vue runs only as small "islands" for the quote form, the review slider and the mobile menu.
- **Admin panel:** Vue 3 SPA via Inertia.js, behind a login at `/admin`.
- **Pros:** One codebase, one deploy, and no Node on the production server. It works on a VPS and on most cPanel hosting. Publishing is instant.
- **Cons:** The public templates are Blade, not `.vue` files.

### Option B: Laravel API + Nuxt 3 (Vue) Static Site Generation
- Laravel provides the admin API and the DB. Nuxt runs `nuxt generate`, which builds a fully static site.
- **Pros:** The public pages are 100% Vue components.
- **Cons:** Each publish needs a Node rebuild of the site, which takes 30s to several minutes. The server needs Node, we deploy two apps, and the hosting is more complex.

### Option C: Vue SPA only
- ❌ Rejected. It is bad for SEO and ranking, and it fails the client's main requirement.

> **✅ DECIDED (2026-10-27):** Option **A**: Laravel SSR + Static HTML Cache.

---

## 3. Site Map & URL Structure (SEO-friendly)

| Page | URL | Type |
|---|---|---|
| Home | `/` | Fixed page, sections editable |
| About Us | `/about-us` | Fixed page, sections editable |
| Moving Services (index) | `/services` | Listing of all services |
| Single Service | `/services/{slug}`, e.g. `/services/local-moving` | **Dynamic** |
| Service Areas (index) | `/service-areas` | Listing / map of all areas |
| Single Area | `/service-areas/{slug}`, e.g. `/service-areas/dallas-tx` | **Dynamic** |
| Area × Service ✅ confirmed | `/service-areas/dallas-tx/local-moving` | Dynamic. Built from a template with `{city}` / `{state}` / `{service}` tokens. Each combination can have its own content override and an active toggle. |
| Reviews | `/reviews` | Listing |
| Contact Us | `/contact-us` | Fixed page plus contact form |
| Get a Free Quote | `/get-a-quote` | Form page. The quote form also appears as a modal or section on other pages |
| Blog / Moving Guides | `/blog` | Listing |
| Blog Category | `/blog/category/{slug}` (Moving Tips, Packing Guides, Moving Cost Guides) | **Dynamic** |
| Blog Post | `/blog/{slug}` | **Dynamic** |
| Legal | `/privacy-policy`, `/terms` | Fixed, editable |
| Custom pages | `/{slug}` | Admin can create extra pages |
| Thank-you page | `/thank-you` | Conversion tracking |
| 404 | n/a | Custom |
| `sitemap.xml`, `robots.txt` | n/a | Auto-generated |

---

## 4. Functional Requirements

### 4.1 Public Website
1. **Header:** Logo, main menu (managed in admin, with dropdowns for Services and Areas), a click-to-call phone number, and a **Get a Free Quote** button.
2. **Footer:** Columns managed in admin, NAP (Name, Address, Phone), social links, license numbers (USDOT / MC #), and copyright.
3. **Home page sections**, inspired by the reference site and each one editable:
   Hero (headline, subhead, background image, CTAs, inline quote form), trust bar (stats such as "10,000+ moves" and badges), services grid, how-it-works steps, why-choose-us cards, service areas grid, reviews slider, FAQ accordion, and a final CTA banner.
4. **Service page template:** Hero, intro, rich content, a "what's included" list, pricing or cost-factors block (optional), FAQ, related areas, a quote form CTA, and related blog posts.
5. **Area page template:** City-specific hero, local content, services offered in that city, an embedded Google Map, local reviews, FAQ, and neighboring areas.
6. **Blog:** Listing with pagination, categories, a featured image, author, date, reading time, table of contents, related posts, and social share.
7. **Reviews page:** Reviews entered by the admin (name, city, rating, text, date, source such as Google/Yelp, an optional photo) and an aggregate rating.
8. **Quote form:** A **single step** with exactly these fields and nothing else: Full Name, Email Address, Phone Number, Moving From, Moving To, Moving Date, Type of Move, and Submit Request.
   - **Moving From / Moving To** use **Google Places Autocomplete**, limited to US addresses. The formatted address, city, state, ZIP and lat/lng are stored in hidden fields.
   - **Type of Move** is a dropdown fed from the admin **Move Types** module. It is independent of Services.
   - Client- and server-side validation, a honeypot plus rate limit, TCPA consent text, and a redirect to `/thank-you`.
   - **Cloudflare Turnstile** comes in the **last task** of the build.
9. **Contact form:** Name, email, phone and message, saved and emailed.
10. **Fully responsive.** Mobile first, with a sticky "Call / Get Quote" bar on mobile.
11. **Branding and theme:** The style follows the reference site. Colors are **CSS variables that the admin can edit in Settings**:
    | Token | Default | Use |
    |---|---|---|
    | `--color-primary` | `#FFFFFF` (white) | Page backgrounds and cards |
    | `--color-secondary` | `#0A2E5C` (logo navy) | Text, headings, header/footer, dark sections |
    | `--color-tertiary` | `#F66419` (logo orange) | Buttons, CTAs, highlights, links |
    | `--color-muted` | `#F1F5FA` | Alternating section backgrounds |
    | `--color-border` | `#DCE4EE` | Borders and inputs |
    - Font: **Inter** (self-hosted).
    - Tailwind maps `primary`, `secondary` and `tertiary` to these variables, so a color change in admin updates the whole site after a cache rebuild.
12. **Single company only.** The site belongs to one moving company. There are **no mover accounts, no mover listings and no lead selling or distribution**, unlike the reference site.

### 4.2 SEO Requirements (built in, not optional)
- Pages are pre-rendered or static HTML with **one H1 per page** and a clean H2/H3 hierarchy.
- **Per-page SEO fields in admin, on every public URL:** fixed pages, each service, area, area×service, post and category, **and** the listing pages (`/services`, `/service-areas`, `/blog`, `/reviews`). The fields are meta title (with a 60-character counter), meta description (with a 160-character counter), a Google snippet preview, slug, canonical URL, OG title, OG description, OG image, robots (index/noindex, follow/nofollow).
- **JSON-LD schema:** `MovingCompany` / `LocalBusiness` (sitewide), `Service` (service pages), `Place` with `areaServed` (area pages), `BlogPosting` and `BreadcrumbList`, `FAQPage` (FAQ blocks), `AggregateRating` and `Review`. Review schema is used only where Google allows it.
- An **XML sitemap** that updates automatically, a `robots.txt` editable in admin, and breadcrumbs on all inner pages.
- A **301 redirect manager** in admin, with an automatic redirect when a slug changes.
- **Performance targets:** Lighthouse score of 90+ on mobile, and Core Web Vitals passing (LCP < 2.5s, CLS < 0.1).
  - WebP images, responsive `srcset`, lazy loading, explicit width and height, critical CSS, minified assets, no render-blocking JS, and fonts loaded with `font-display: swap`.
- **Image alt text** is required in the media library.
- **Internal linking:** related services, areas and posts are linked automatically.
- **Tracking fields in admin:** Google Analytics 4, Google Tag Manager, Search Console verification, Facebook Pixel, and custom `<head>` / `<body>` scripts.
- Trailing-slash and lowercase URL normalisation, and HTTPS-only.

### 4.3 Admin Panel (full control)
**Auth & users**
- Secure login and forgot password.
- ~~One role only: Admin.~~ **Changed (T19):** roles with permissions; the Administrator role has full access. See `02-build-prompt.md` §14.1.

**Dashboard**
- Leads today, this week and this month, recent leads, recent posts, and quick links.

**Page builder (section-based)**
- Every page (Home, About, Contact and others) is made of **sections**, and the admin can **add, remove, reorder (drag & drop) and hide or show** them.
- Section types: Hero, Rich Text, Image + Text, Features/Cards grid, Steps, Stats, Services Grid, Areas Grid, Reviews Slider, FAQ, CTA Banner, Quote Form, Gallery, Custom HTML.
- Each section has its own heading, subheading, content (rich text editor such as TipTap), images, buttons (label + URL) and background options.
- The page as a whole has SEO fields, a draft or published status, and a **Preview** before publishing.

**Moving Services (dynamic)**
- Full CRUD with no limit on the number of services.
- Fields: title, slug, short description, icon/image, hero image, content sections (same builder as pages), FAQs, display order, active/inactive, show in menu, show on home, and SEO fields.

**Service Areas (dynamic)**
- Full CRUD.
- Fields: city, state, slug, hero, local content sections, services available here (multi-select), map embed or lat/lng, ZIP codes served, nearby areas, FAQs, order, active, and SEO fields.

**Blog / Moving Guides**
- **Categories:** CRUD, starting with Moving Tips, Packing Guides and Moving Cost Guides, each with SEO fields.
- **Posts:** CRUD with title, slug, category, tags, author, featured image, excerpt, rich content (headings, images, tables, embeds), and SEO fields. Status can be draft, scheduled or published, and the publish date can be scheduled.
- **Authors:** name, bio and photo, for E-E-A-T.

**Move Types:** CRUD for the quote form's "Type of Move" dropdown, e.g. Local, Long-Distance, Residential, Commercial, Apartment or Office. Fields: name, order and active.

**Area × Service pages:** A default template with tokens, a grid to switch combinations on or off, a per-combination content override, and SEO fields.

**Reviews:** CRUD, a star rating, featured on/off, and assignment to an area and/or service.

**FAQs:** A global FAQ bank. FAQs can be attached to any page, service or area.

**Media Library**
- Upload images by drag & drop and edit them in the **cropper**.
- **Crop presets** with fixed sizes, for example:
  - Hero 1920×800
  - Card 600×400
  - Blog featured 1200×630, which doubles as the OG image
  - Square 600×600
  - Logo (free crop)
- The server generates the crop plus WebP versions and responsive sizes (e.g. 480 / 768 / 1200 / 1920) and stores everything in `storage/app/public/media/YYYY/MM/`.
- Alt text, title, search, filter, a usage check before delete, and a max upload size and allowed types (jpg, png, webp, svg for logos only).

**Leads (Quote Requests)**
- A list with filters by date, move type and status, plus search.
- Status workflow: New, Contacted, Quoted, Booked, Lost. Internal notes and **CSV export**.
- **Email only:** a notification to one or more admin addresses, and an auto-reply email to the customer (template editable). No SMS and no CRM.
- Captured with each lead: source page, UTM parameters, IP and user agent.

**Contact messages:** Inbox with read/unread.

**Menus:** A drag & drop editor for the header and footer menus, which can link to pages, services, areas, posts or custom URLs.

**Site Settings**
- Company name, logos (light/dark), favicon, phone, email, address, business hours, USDOT / MC numbers, and social links.
- Theme colors (primary, secondary, tertiary, muted, border) and the default SEO and OG image.
- Tracking codes, SMTP email settings, the Google Places API key and the Cloudflare Turnstile keys.

**SEO tools:** Redirect manager, robots.txt editor, sitemap regeneration, and a **"Rebuild static cache"** button.


---

## 5. Non-Functional Requirements
- **Security:** CSRF, XSS sanitization of rich text (HTML Purifier), prepared statements via Eloquent, bcrypt passwords, login throttling, an admin route that can be renamed, secure headers, and file upload validation (MIME and size) with no PHP execution allowed in the uploads folder.
- **Backups:** Daily DB dump plus a media backup (spatie/laravel-backup), as long as the hosting allows cron.
- **Compliance:**
  - Privacy Policy and Terms pages.
  - TCPA consent text on the forms, since US movers call and text leads.
  - A cookie banner (optional, US).
  - Accessibility at WCAG 2.1 AA level (alt text, contrast, keyboard navigation).
- **Browser support:** Latest 2 versions of Chrome, Safari, Edge and Firefox, plus iOS Safari and Android Chrome.
- **Code quality:** Git repo, a `.env`-based config, migrations, seeders with demo content, and a README with deploy steps.

---

## 6. Tech Stack (proposed, Option A)
| Layer | Choice |
|---|---|
| Language / Framework | PHP 8.3, **Laravel 13** |
| Database | **MySQL 8** |
| Admin UI | **Vue 3 + Inertia.js + Vite**, Tailwind CSS, TipTap editor, vue-advanced-cropper, SortableJS (drag & drop) |
| Public UI | Blade templates, Tailwind CSS, small Vue 3 / Alpine.js islands |
| Images | Intervention Image v3 (GD/Imagick) with WebP output |
| Static cache | Custom publisher that writes HTML to `public/static`, plus a Nginx/Apache rewrite to serve those files first |
| Email | Laravel Mail (SMTP) with queued jobs |
| SEO | spatie/laravel-sitemap and a JSON-LD helper |
| Address autocomplete | Google Maps JS API, Places library (US only) |
| Spam | Honeypot and rate limit, plus Cloudflare Turnstile (last task) |
| Server | **cPanel** (Apache + PHP 8.3). The domain points to `/public`, or `/public_html` uses an `.htaccess` bridge. Cron runs `schedule:run` every minute, and assets are pre-built with Vite. |

---

## 7. Database (high-level entities)
`users`, `pages`, `move_types`, `area_service_pages`, `sections` (polymorphic: belongs to page / service / area / post), `services`, `areas`, `area_service` (pivot), `posts`, `categories`, `tags`, `post_tag`, `authors`, `reviews`, `faqs`, `faqables` (pivot), `media`, `media_variants`, `menus`, `menu_items`, `leads`, `lead_notes`, `contact_messages`, `settings` (key/value), `redirects`, `seo_meta` (polymorphic).

---

## 8. Deliverables
| # | Deliverable |
|---|---|
| D1 | This signed-off requirements doc |
| D2 | Comprehensive build prompt / technical spec (next step after sign-off) |
| D3 | UI design direction: homepage plus 1 inner page mockup (HTML/Tailwind) for approval |
| D4 | Laravel + Vue source code in a Git repo |
| D5 | MySQL migrations and seeders with demo content: the 6 services, 3 areas (Dallas, Plano, Irving), 3 blog categories and sample posts |
| D6 | Fully working admin panel (every module in §4.3) |
| D7 | Public website (every page in §3) with the SEO features in §4.2 |
| D8 | Static cache publisher, sitemap, schema and redirects |
| D9 | cPanel deployment guide, `.env.example`, `.htaccess` rules and cron setup |
| D10 | Admin user guide: how to add a service, area or post, and how to crop images |
| D11 | QA checklist: Lighthouse report, form tests, mobile tests and an SEO validation (Rich Results Test) |

### Proposed phases
1. **Phase 1, Foundation:** Laravel setup, auth, settings, media library plus cropper, DB schema.
2. **Phase 2, Content modules:** Pages and section builder, Services, Areas, Blog, Reviews, FAQs, Menus.
3. **Phase 3, Public site:** Templates, quote form, leads module, emails.
4. **Phase 4, SEO & performance:** Schema, sitemap, static cache, redirects, Lighthouse tuning.
5. **Phase 5, QA & deploy:** Testing, docs, go-live.

---

## 9. Out of Scope (unless the client adds it)
- **Any mover features:** mover accounts, mover directory or listings, lead selling or distribution
- User roles and permissions, and an activity log
- SMS lead alerts
- Online payments or booking deposits
- A moving cost calculator with live pricing
- CRM integrations (HubSpot, SmartMoving, Granot). Leads can be exported or sent by webhook later.
- Multi-language support
- A customer login portal
- Writing the actual SEO content and copy. We seed placeholder content.
- Live Google Reviews API sync. Reviews are entered manually.

---

## 10. Decisions Log (finalized 2026-10-27)
| # | Topic | Decision |
|---|---|---|
| 1 | Architecture | **Option A**: Laravel SSR + static HTML cache |
| 2 | Hosting | **cPanel** (Apache, no Node on the server) |
| 3 | Quote form | **Single step.** Only the fields listed: Name, Email, Phone, From, To, Date, Type of Move |
| 4 | Branding | Style follows the reference site. **Primary = white, secondary = navy, tertiary = orange**, all CSS variables editable in admin |
| 5 | Area × Service pages | **Yes** |
| 6 | Roles | ~~Admin only~~ → **Roles and permissions (T19)** |
| 7 | Lead alerts | **Email only** |
| 8 | Spam protection | **Cloudflare Turnstile**, built in the **last** task |
| 9 | Content | **SEO-ready placeholder copy** in the seeders |
| 10 | Crop sizes | As listed in §4.3 |
| 11 | Address input | **Google Places Autocomplete** on Moving From / Moving To (US only) |
| 12 | Type of Move | **Managed in admin** (Move Types module) |
| 13 | Meta title / description | **Editable in admin for every public URL** |
| 14 | Movers | **No mover features at all.** Single-company site |
| 15a | Framework version | **Laravel 13** instead of 12, because 12 is out of bug-fix support. Same PHP 8.3 requirement |
| 15 | Dev environment | **Laravel Sail (Docker)**. Production remains cPanel without Docker |
| 16 | Client launch checklist | 10 more client requirements (mobile, Core Web Vitals, unique title / description / H1, sitemap and robots, no accidental noindex, HTTPS, LocalBusiness schema, Call Now + Free Quote on every page, visible contact info, GA4 + Search Console). Each one has a verification step: **build prompt §11.1** |
