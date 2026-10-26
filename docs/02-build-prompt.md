# BUILD PROMPT: USA Moving Company Website (Laravel SSR + Static Cache + Vue Admin)

> Give this whole file to the implementing engineer or AI agent. It is the single source of truth.
> Read `01-requirements-and-feasibility.md` for the background. Where the two disagree, **this file wins**.

---

## 0. Role & Mission

You are a senior full-stack engineer. Build a **production-ready, SEO-first marketing website** for **one US moving company** (Dallas–Fort Worth area), plus an **admin panel that controls everything**.

The public site must deliver **fully rendered static HTML** for ranking and speed. The admin panel must let a non-technical admin change every heading, text block, image, meta tag, menu, color, service, area, blog post, review, FAQ and quote-form option. **No code changes should be needed.**

**Hard rules**
1. The public HTML is complete without JavaScript. JS only adds interactivity.
2. Every public URL has admin-editable **meta title** and **meta description**.
3. Services, Service Areas, Area×Service pages, Blog Categories, Blog Posts and Move Types are **unlimited and dynamic**. One template per type.
4. All media is stored **on our server** (Laravel `public` disk), cropped to presets and converted to WebP.
5. **There are no mover or marketplace features.** No mover accounts, directories, or lead selling or distribution.
6. There is **one admin role**. Multiple admin users are allowed, all with full access, and there is no permission system.
7. Lead alerts go out **by email only**.
8. The quote form is **a single step** with **exactly** these fields: Full Name, Email Address, Phone Number, Moving From, Moving To, Moving Date, Type of Move, and a Submit Request button. **Add no other fields.** The honeypot and the hidden address-detail fields are allowed.
9. It must run on **cPanel shared hosting** with **no Node on the server**.
10. **Cloudflare Turnstile is the LAST task** (T18). Until then, spam protection is a honeypot plus a rate limit.

---

## 1. Tech Stack (pin these)

| Area | Choice |
|---|---|
| Runtime | PHP **8.3**, MySQL **8.0** (utf8mb4_unicode_ci) |
| Framework | **Laravel 13** (13.38). Laravel 12 bug-fix support ended Aug 2026, so we use the current release. Composer platform is pinned to PHP 8.3. |
| Admin UI | **Laravel Vue starter kit** (current `main`): Inertia 3, Vue 3.5, TypeScript, Tailwind CSS 4, shadcn-vue (reka-ui), Fortify auth, Wayfinder typed routes, Vite+ build. Features kept: login, forgot/reset password, password confirmation. **Registration, email verification, 2FA and passkeys are off.** |
| Admin libraries | `@tiptap/vue-3` (rich text), `vue-advanced-cropper` (cropper), `vuedraggable@next` (sorting), `@vueuse/core` |
| Public UI | **Blade** templates and components, Tailwind CSS 4, small **Vue 3 islands** (quote form, reviews slider, FAQ accordion, mobile menu) |
| Images | `intervention/image` v3 with the **GD driver**, because cPanel often lacks Imagick |
| SEO | `spatie/laravel-sitemap`, custom JSON-LD builder |
| HTML sanitize | `mews/purifier` for every rich-text field on save |
| Queue | `database` driver, processed from cron |
| Mail | Laravel Mail over SMTP, with credentials from admin Settings and `.env` as the fallback |
| Address autocomplete | **Google Maps JS API, Places API (New)**: `AutocompleteSuggestion` with session tokens, `includedRegionCodes: ['us']`. **Do not use the legacy `places.Autocomplete`**, which is unavailable to new API keys. |
| Testing | Pest (feature tests for forms, publisher, redirects, SEO output) |
| **Dev environment** | **Laravel Sail (Docker)**. See §1.1. Production stays **cPanel without Docker**. |

### 1.1 Development Environment: Laravel Sail
Docker 28.5 and Docker Desktop are installed on the dev Mac. **No PHP, Composer or MySQL is installed locally, and none is needed.** Every command runs in containers.

**Pin the versions to match cPanel:**
- `laravel.test` runs the Sail **PHP 8.3** runtime (`sail:install --php=8.3`).
- `mysql` runs the **`mysql:8.0`** image, not 8.4.
- Force the **GD** image driver in `config/image.php`, even though the Sail image also ships Imagick, so dev behaves like cPanel.

**Compose services** (`compose.yaml`):
| Service | Purpose |
|---|---|
| `laravel.test` | App (PHP 8.3) plus Node/npm for Vite. Exposes `APP_PORT=80` and `VITE_PORT=5173`. |
| `mysql` | MySQL 8.0 with a named volume `sail-mysql`. A healthcheck gates app startup. |
| `mailpit` | Catches all mail. UI on `:8025`, SMTP on `:1025`. |
| `queue` | Same image, runs `php artisan queue:work --tries=3 --timeout=120`. Restarts unless stopped. |
| `scheduler` | Same image, runs `php artisan schedule:work` (scheduled posts, backups). |
| `apache` *(profile `apache`)* | `php:8.3-apache` with `mod_rewrite` and `mod_headers`, document root `/var/www/html/public`, port `8080`. **It tests the real `.htaccess` static-cache rules exactly as cPanel will run them.** Start it with `make apache` (`docker compose --profile apache up -d apache`). |

**Bootstrap** (T0). Composer runs inside a container because the host has no PHP:
```bash
# project root already contains docs/, and create-project needs an empty dir → build in a temp dir, then move up
docker run --rm -v "$PWD":/app -w /app composer:2 create-project laravel/vue-starter-kit _skeleton dev-main --stability=dev
cp -R _skeleton/. . && rm -rf _skeleton
docker run --rm -v "$PWD":/app -w /app composer:2 require laravel/sail --dev
docker run --rm -v "$PWD":/app -w /app composer:2 php artisan sail:install --with=mysql,mailpit --php=8.3
# then edit compose.yaml: pin mysql:8.0, add queue / scheduler / apache services
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate && ./vendor/bin/sail artisan migrate --seed && ./vendor/bin/sail artisan storage:link
./vendor/bin/sail npm install && ./vendor/bin/sail npm run dev
```

**Developer conveniences**
- Add `alias sail='sh $([ -f sail ] && echo sail || echo vendor/bin/sail)'` to the README.
- Add `Makefile` targets: `make up`, `make down`, `make fresh` (migrate:fresh --seed), `make test`, `make build`, `make rebuild-static`, `make apache`.
- `.env.example` uses Sail defaults: `DB_HOST=mysql`, `MAIL_HOST=mailpit`, `MAIL_PORT=1025`, `QUEUE_CONNECTION=database`.
- `.dockerignore` and `.gitignore` cover `vendor`, `node_modules`, `public/build`, `public/static`, `storage/app/public/media` and `.env`.
- **Do not commit `public/static`.** It is generated per environment.

**Vite inside Docker:** Set `server.host: '0.0.0.0'`, `hmr.host: 'localhost'` and port `5173` in `vite.config.ts`, so hot reload works from the container.

**File-sync performance (macOS):** In Docker Desktop, enable **VirtioFS**. If it is still slow, add a named volume for `node_modules`.

Use two Vite entry points:
- `resources/js/admin/app.ts`: the Inertia admin.
- `resources/js/site/site.ts` plus `resources/css/site.css`: the public site. Keep the public JS **≤ 60 KB gzipped**.

---

## 2. Brand & Theme

The style follows https://www.movinglead.co: a clean white layout, navy text, orange CTAs, Inter, generous whitespace, rounded cards, a subtle grid pattern on dark sections, and bold short headlines.

**Colors are CSS variables** stored in `settings` and printed in `<head>`:
```css
:root{
  --color-primary:#FFFFFF;   /* backgrounds, cards */
  --color-secondary:#0D1B2A; /* navy: text, header/footer, dark sections */
  --color-tertiary:#F4632A;  /* orange accent: buttons, links, highlights */
  --color-muted:#F1F4F7;     /* alternating section bg */
  --color-border:#E1E7EE;
}
```
- Tailwind 4 `@theme` maps `primary`, `secondary`, `tertiary`, `muted` and `border` to these variables, so `bg-tertiary` and `text-secondary` work.
- Derive hover and tint shades with `color-mix()` so the admin only edits the 5 base colors.
- Font: **Inter**, self-hosted woff2 (400/500/600/700/800), `font-display: swap`, with a preload for 600 and 800.
- Admin **Settings → Theme** shows color pickers with a live preview swatch. Saving triggers a full static rebuild.

Public components:
- Buttons: `primary` (tertiary background, white text), `outline`, `ghost`.
- Section container `max-w-7xl`, section padding `py-16 md:py-24`, cards `rounded-2xl border shadow-sm`.

---

## 3. URL Map & Templates

| Route name | URL | Template | Source |
|---|---|---|---|
| home | `/` | `pages/show` | `pages` (system key `home`) |
| about | `/about-us` | `pages/show` | `pages` (`about`) |
| services.index | `/services` | `services/index` | `pages` (`services_index`) plus all active services |
| services.show | `/services/{service:slug}` | `services/show` | `services` |
| areas.index | `/service-areas` | `areas/index` | `pages` (`areas_index`) plus areas grouped by state |
| areas.show | `/service-areas/{area:slug}` | `areas/show` | `areas` |
| areas.service | `/service-areas/{area:slug}/{service:slug}` | `areas/service` | `area_service_pages` (404 if inactive) |
| reviews | `/reviews` | `reviews/index` | `pages` (`reviews_index`) plus reviews |
| contact | `/contact-us` | `pages/show` | `pages` (`contact`) |
| quote | `/get-a-quote` | `pages/show` | `pages` (`quote`) |
| thank-you | `/thank-you` | `pages/show` | `pages` (`thank_you`), always `noindex` |
| blog.index | `/blog` (`?page=N` → `/blog/page/N`) | `blog/index` | `pages` (`blog_index`) plus posts |
| blog.category | `/blog/category/{category:slug}` (+ `/page/N`) | `blog/category` | `categories` |
| blog.show | `/blog/{post:slug}` | `blog/show` | `posts` (published, `published_at <= now`) |
| privacy / terms | `/privacy-policy`, `/terms` | `pages/show` | `pages` |
| page.custom | `/{page:slug}` (catch-all, registered last) | `pages/show` | `pages` (non-system) |
| sitemap | `/sitemap.xml` | n/a | generated file |
| robots | `/robots.txt` | n/a | from settings |
| form endpoints | `POST /forms/quote`, `POST /forms/contact` | JSON | n/a |
| admin | `/admin/*` | Inertia | auth required |

Rules:
- Lowercase slugs. **No trailing slash.** A trailing slash gets a 301 to the version without it.
- Paginated pages use path segments (`/page/2`), so they can be cached statically.
- Reserved slugs that cannot be used for custom pages: `admin, services, service-areas, blog, reviews, forms, storage, static, build, sitemap.xml, robots.txt`.

---

## 4. Database Schema

Write all of this as Laravel migrations. Every content table gets `timestamps()`, and `softDeletes()` where noted (sd).

```
users              id, name, email (unique), password, remember_token, last_login_at
settings           id, group, key (unique), value (json)       -- cached forever, cache busted on save
media              id, disk, folder (YYYY/MM), filename, original_path, mime, size, width, height,
                   alt, title, caption, preset (nullable), crop (json: x,y,w,h,rotate)   (sd)
media_variants     id, media_id FK, width, height, format (webp|jpg|png), path, size
seo_meta           id, seoable_type, seoable_id, meta_title, meta_description, canonical_url,
                   og_title, og_description, og_image_id FK media, robots_index bool=1,
                   robots_follow bool=1, schema_extra (json, nullable)       -- unique(seoable_type, seoable_id)
pages              id, system_key (nullable unique), title, slug (unique), status (draft|published),
                   template (default), published_at                                     (sd)
sections           id, sectionable_type, sectionable_id, type, data (json), sort, is_visible bool
services           id, title, slug (unique), short_description, icon_media_id, image_media_id,
                   hero_media_id, sort, is_active, show_in_menu, show_on_home                (sd)
areas              id, city, state_name, state_code (char2), slug (unique, e.g. dallas-tx),
                   intro, hero_media_id, image_media_id, lat, lng, zip_codes (json), map_embed (text),
                   sort, is_active, show_in_menu                                            (sd)
area_service       area_id, service_id   (services offered in area; PK both)
area_neighbors     area_id, neighbor_id
area_service_pages id, area_id, service_id, is_active, use_template bool=1, title_override,
                   unique(area_id, service_id)     (+ sections & seo_meta via morph)
move_types         id, name, sort, is_active
categories         id, name, slug (unique), description, sort                              (sd)
authors            id, name, slug, bio, photo_media_id, job_title
tags               id, name, slug
posts              id, category_id FK, author_id FK, title, slug (unique), excerpt, content (longtext html),
                   featured_media_id, status (draft|scheduled|published), published_at, reading_minutes,
                   is_featured                                                              (sd)
post_tag           post_id, tag_id
reviews            id, name, city, rating (1-5), body, source (google|yelp|facebook|website|other),
                   reviewed_at, photo_media_id, area_id (nullable), service_id (nullable),
                   is_featured, is_active, sort
faqs               id, question, answer (html), sort, is_active
faqables           faq_id, faqable_type, faqable_id, sort
menus              id, location (header|footer_1|footer_2|footer_3|mobile) unique, title
menu_items         id, menu_id, parent_id, label, link_type (page|service|area|post|category|url),
                   linkable_id, url, target_blank, sort
leads              id, full_name, email, phone, from_address, from_city, from_state, from_zip,
                   from_lat, from_lng, to_address, to_city, to_state, to_zip, to_lat, to_lng,
                   move_date, move_type_id FK, move_type_name (snapshot), status
                   (new|contacted|quoted|booked|lost), source_url, referrer, utm (json), ip,
                   user_agent, read_at                                                       (sd)
lead_notes         id, lead_id, user_id, body
contact_messages   id, name, email, phone, message, source_url, ip, read_at                 (sd)
redirects          id, from_path (unique), to_path, status_code (301|302), hits, last_hit_at, is_active
static_builds      id, path, status (ok|failed), error, built_at          -- publisher log
jobs, failed_jobs, cache, sessions   -- Laravel defaults
```

Models:
- Traits `HasSeo` (morphOne `seo_meta`, plus `seoTitle()` / `seoDescription()` fallbacks), `HasSections` (morphMany, ordered) and `HasFaqs` (morphToMany).
- Every slugged model gets `HasSlugRedirect`. When a slug changes, it automatically creates a 301 in `redirects` from the old URL to the new one.
- Media references are FKs to `media` with `nullOnDelete`. A media item cannot be deleted while it is in use; the "Usage" panel lists every reference.

---

## 5. Section Builder (the core of the "admin controls every heading and content block" requirement)

Define each section type **once** in `config/sections.php`. That definition drives (a) the admin form, which is generated automatically, (b) validation, and (c) the Blade component `resources/views/sections/{type}.blade.php`.

```php
'hero' => [
  'label' => 'Hero',
  'fields' => [
    ['key'=>'eyebrow','type'=>'text'],
    ['key'=>'heading','type'=>'text','required'=>true],      // rendered as H1 if first hero on page, else H2
    ['key'=>'subheading','type'=>'textarea'],
    ['key'=>'bullets','type'=>'repeater','fields'=>[['key'=>'text','type'=>'text']]],
    ['key'=>'primary_button','type'=>'link'],                // {label,url}
    ['key'=>'secondary_button','type'=>'link'],
    ['key'=>'background','type'=>'image','preset'=>'hero'],
    ['key'=>'show_quote_form','type'=>'toggle'],
    ['key'=>'theme','type'=>'select','options'=>['light','dark']],
  ],
],
```

Field types: `text, textarea, richtext (TipTap), image (with preset), link, toggle, select, number, repeater, icon (Lucide name picker), service_picker, area_picker, review_picker, faq_picker`.

Required section types:
`hero, trust_bar (stats + badges), rich_text, image_text (image left/right), cards_grid (icon/title/text), steps (numbered how-it-works), services_grid (auto: all or picked), areas_grid (auto: all or picked), reviews_slider (auto: featured or picked, AggregateRating), faq (picked FAQs → FAQPage schema), cta_banner, quote_form, contact_form, gallery, map, pricing_factors, custom_html`.

Admin UX:
- A left column lists sections. Each row has drag handles, an eye toggle, duplicate and delete.
- The right column shows the generated form for the selected section.
- Buttons: "Add section" (picker with thumbnails), **Save draft**, **Preview** (opens `/admin/preview/{type}/{id}`, which renders the live template with draft data and `noindex`), and **Publish**.
- Headings: the template guarantees **exactly one H1 per page**. That is the first hero heading, or the page title if there is no hero.

**Area×Service tokens:** Text fields support `{city} {state} {state_code} {service} {company} {phone}`, replaced at render time.
- The admin edits ONE default Area×Service template (sections and SEO stored on `pages` with system key `area_service_template`).
- Each `area_service_pages` row either uses that template or overrides it (`use_template=0` → its own sections and SEO).
- The grid view (areas × services) has toggles to switch combinations on or off and an "Override content" button.
- Default meta template: `"{service} in {city}, {state_code} | {company}"`.

---

## 6. Admin Panel Modules (`/admin`)

Use a sidebar layout. Every list has search, filters, pagination, sortable columns, bulk delete and an active toggle. Every form has validation errors inline, an unsaved-changes guard and toast notifications.

1. **Auth:** Login, forgot/reset password, profile (name, email, password), and **Users** CRUD (admins only, no roles). Login throttle: 5 attempts per minute.
2. **Dashboard:** Lead counts (today, 7 days, 30 days), a chart of leads per day over 30 days, the latest 10 leads, unread contact messages, the last static build status and a "Rebuild site" button.
3. **Pages:** CRUD. System pages cannot be deleted, and their slugs are locked for `home`. Includes the section builder, an SEO tab and a status field.
4. **Services:** CRUD with fields (§4), the section builder, FAQs, an SEO tab and drag sorting.
5. **Service Areas:** CRUD, services offered (multi-select), neighbors, ZIPs (tag input), map embed or lat/lng, the section builder, FAQs and an SEO tab.
6. **Area × Service:** The grid (§5), the default template editor and per-combination overrides.
7. **Move Types:** CRUD and sorting. This feeds the quote form dropdown. Seed it with Local Move, Long-Distance Move, Residential Move, Commercial Move, Apartment Move and Office Move.
8. **Blog**
   - **Posts:** TipTap editor with H2–H4, lists, quotes, tables, images from the media library, links and YouTube embeds.
   - Category, author, tags, a featured image (preset `blog`), an excerpt, status (draft / scheduled / published), `published_at`, featured, and an SEO tab.
   - Reading time is calculated automatically.
   - The table of contents is generated automatically from H2s on render.
   - **Categories:** CRUD with SEO. **Authors:** CRUD.
9. **Reviews:** CRUD, rating stars, source, area and service links, and featured.
10. **FAQs:** A global bank. FAQs are attached from any page, service or area via the picker.
11. **Media Library:** Grid and list view, folders by month, search, upload (drag & drop, multiple files), edit alt/title/caption, usage panel, replace file.
    - Allowed types: jpg/jpeg/png/webp (≤ 8 MB) and svg (logos only, sanitized).
12. **Image Cropper** (`vue-advanced-cropper`), opened on upload and on any image field:
    | Preset | Size | Aspect |
    |---|---|---|
    | `hero` | 1920×800 | 12:5 |
    | `card` | 600×400 | 3:2 |
    | `blog` | 1200×630 | 1.91:1 (also used as the OG image) |
    | `square` | 600×600 | 1:1 |
    | `logo` | free | free (keeps PNG/SVG transparency) |
    - The client sends the original file plus the crop box `{x,y,w,h,rotate}` and the preset.
    - The server (GD) crops and resizes to the preset size, then generates responsive widths `[480, 768, 1200, 1920]` (only those ≤ the preset width) in **WebP (q=80)** and **JPG/PNG (q=82)** fallback.
    - Output goes to `storage/app/public/media/YYYY/MM/{uuid}-{w}.{ext}`, and the original is kept.
    - **Re-crop** is possible later from the stored original.
13. **Menus:** Drag & drop nested editor (2 levels) for the header, three footer columns and mobile. Items link to page, service, area, post, category or a URL.
    - Toggle: "auto-list all services/areas under this item".
14. **Leads (Quote Requests):**
    - Table with filters (status, date range, move type) and search (name, email, phone).
    - Detail view: all fields, a Google Maps link for each address, status changer, notes, source URL and UTM.
    - Mark read/unread, **CSV export** (filtered) and soft delete.
15. **Contact Messages:** Inbox, read/unread and delete.
16. **SEO Tools**
    - **Redirects:** CRUD, hit counts, CSV import.
    - **robots.txt:** editor.
    - **Sitemap:** "Regenerate".
    - **Defaults:** title suffix (e.g. `| Company Name`), default meta description, default OG image.
    - **Missing-SEO report:** lists URLs without a custom meta title or description, and titles or descriptions that are too long or duplicated.
17. **Settings** (tabs):
    - **General:** company name, legal name, phone, email, address (street, city, state, ZIP), business hours, USDOT #, MC #, founded year, service radius text, social links.
    - **Branding:** logo (light and dark), favicon.
    - **Theme:** the 5 colors.
    - **Email:** SMTP host, port, user, password (encrypted), from name, from address, **lead notification recipients** (multiple), auto-reply on/off, subject and body (tokens `{name}` `{move_date}` …).
    - **Integrations:** Google Places API key, GA4 ID, GTM ID, Search Console verification, Facebook Pixel, custom head and body scripts. Turnstile keys are added in T18.
    - **Forms:** TCPA consent text, quote form button label, success message.
    - **Static Cache:** "Rebuild all", last build log, and an on/off toggle (off = live SSR, for debugging).

Admin SEO tab (a shared component on every content form):
- Meta title (live character counter, warning above 60) and meta description (counter, warning above 160).
- A **Google SERP preview**.
- Canonical URL, OG title, OG description and OG image (cropper preset `blog`).
- Index/noindex and follow/nofollow.
- Fallbacks when empty:
  - title → `{record title} {suffix}`
  - description → excerpt or short description, stripped and trimmed to 155 characters

---

## 7. Public Site Templates

**Layout (`layouts/site.blade.php`)**
- `<head>`:
  - Title, meta description, canonical, robots, OG and Twitter tags.
  - Favicon, preloaded fonts, inline **critical CSS** for above-the-fold content, then the full CSS.
  - Theme variables, JSON-LD, GA4/GTM (deferred), and custom scripts.
- **Top contact bar** (desktop, slim navy strip): phone, email, business hours and service-area text, all from Settings.
- **Header:**
  - Logo and a header menu with Services and Areas dropdowns.
  - Two buttons on **every page**:
    - **Call Now:** an outline button with a phone icon and the number visible, `tel:` link.
    - **Get a Free Quote:** the solid orange button.
  - Sticky on scroll. Mobile drawer menu.
- **Mobile sticky bottom bar** on every page (below 768px): **Call Now** and **Get a Free Quote**, split 50/50 with 48px+ tap targets. Add `padding-bottom` to the body so it never covers content or causes layout shift (CLS).
- **In-content CTAs:** Every service, area, area×service and blog template ends with a CTA banner (Call Now + Get a Free Quote). Blog posts also get one mid-article CTA after the 2nd H2.
- **Footer:**
  - Logo and blurb, three menu columns, NAP block, USDOT and MC numbers, social icons, legal links, `© {year} {company}`.
- **Breadcrumbs** (HTML plus `BreadcrumbList` schema) on every page except home.

**Templates**
- **Home:** Sections from the builder. Seed: hero with the inline quote form, trust_bar, services_grid, steps, cards_grid (why us), areas_grid, reviews_slider, faq, cta_banner.
- **Service:**
  - Hero (H1 = service title by default), sections, the "Available in" list linking to area×service pages, FAQs, related posts (same tags, or latest), and a quote CTA.
- **Areas index:** Areas grouped by state, plus an optional static map image.
- **Area:**
  - Hero ("Movers in {city}, {state_code}"), sections, the "Services in {city}" grid linking to area×service pages, local reviews, map, neighboring areas, FAQs, and a CTA.
- **Area×Service:** The template or override, with links to the parent area, the parent service and sibling services in the same city.
- **Blog index and category:** Featured post, a grid of cards (image, category, title, excerpt, date, reading time), path pagination, and a category nav.
- **Post:**
  - H1 title, meta line (author, date, updated, reading time), featured image, TOC, content, author box (E-E-A-T), related posts, and a CTA.
  - Previous/next links.
- **Reviews:** Aggregate rating header, review cards with source badges, and a CTA.
- **Contact:** Sections plus the contact form plus the NAP block, map and hours.
- **Quote:** Sections plus a prominent quote form.
- **404:** Search suggestions, plus links to services, areas and the quote page.
- **Images:** Rendered only through `<x-picture :media="$m" preset="card" sizes="..." :eager="false" />`.
  - It outputs `<picture>` with a WebP `srcset`, a fallback `img`, explicit `width` and `height`, `loading="lazy"`, `decoding="async"`, and alt text from the media record.
  - Hero images use `eager` and `fetchpriority="high"`.

---

## 8. Quote Form (single step) & Contact Form

**Fields (exact):** Full Name · Email Address · Phone Number · Moving From · Moving To · Moving Date · Type of Move · **Submit Request**.

It is a Vue island `QuoteForm.vue`. The server-rendered HTML contains the full `<form>` markup, so the form still works without JS as a normal POST that redirects to `/thank-you`.

Validation (client and server, Laravel FormRequest):
| Field | Rules |
|---|---|
| full_name | required, 2–100 chars |
| email | required, RFC + DNS email |
| phone | required. US format, normalized to E.164 (`+1XXXXXXXXXX`). Input mask `(XXX) XXX-XXXX`. |
| from_address, to_address | required, 5–255. Hidden fields: city, state, ZIP, lat, lng (filled from Places, nullable) |
| move_date | required date, ≥ today, ≤ today + 18 months. Native `<input type="date">` with `min`. |
| move_type_id | required, `exists:move_types,id,is_active,1` |
| website (honeypot) | must be empty (hidden off-screen, `tabindex=-1`, `autocomplete=off`) |

**Google Places (Moving From / Moving To)**
- Lazy-load the Maps JS **on the first focus** of either address field, never on page load, so the page stays fast.
- Use the `AutocompleteSuggestion.fetchAutocompleteSuggestions` API with an `AutocompleteSessionToken`, `includedRegionCodes:['us']`, and a debounce of 250 ms.
- Show a custom-styled, keyboard-accessible listbox: ARIA combobox, arrow keys and Enter.
- On select, call `place.fetchFields({fields:['formattedAddress','addressComponents','location']})` and fill the hidden fields.
- If the key is missing or the API fails, the fields keep working as plain text inputs.

**Submission**
- `POST /forms/quote`, rate limited to **5 per IP per 10 minutes**. Stores a `leads` row with source URL, referrer, UTM (captured from the landing URL into `sessionStorage`), IP and user agent.
- **CSRF:** The pages are static HTML, so a per-session CSRF token cannot be embedded. Exclude `/forms/*` from CSRF. Instead:
  - Check that the `Origin` or `Referer` header matches `APP_URL`.
  - Rely on the honeypot, the rate limit and, later, Turnstile.
  - Reject submissions made less than 3 seconds after render (a timestamp field signed with HMAC).
- **Emails (queued):**
  1. Admin notification to all recipients in Settings: a branded HTML table of the lead, with a link to the admin lead page.
  2. Customer auto-reply, if enabled, from the editable template.
- Response: JSON `{ok:true, redirect:'/thank-you'}` for JS, or a 302 for non-JS. Push a GA4/GTM `generate_lead` event on success.
- TCPA consent text from Settings is shown under the button. It is text only, because the client allowed no extra fields.

**Contact form:** Name, email, phone, message. Same protections. Saved to `contact_messages` and emailed to the same recipients.

---

## 9. SEO Implementation

- **Meta:** `App\Support\Seo` builds title, description, canonical, robots and OG/Twitter tags from `seo_meta` and the fallbacks. Every template calls `@seo($model)`.
  - Paginated pages get `?page`-free canonicals to themselves, and the title gets " – Page N".
- **JSON-LD** (`App\Support\Schema`), output as a single `@graph`:
  - Sitewide: `MovingCompany` (subtype of LocalBusiness) with name, logo, url, telephone, address, geo, openingHours, sameAs, areaServed (all active areas) and priceRange. Also `WebSite`.
  - Service: `Service` (provider → MovingCompany, areaServed).
  - Area and area×service: `Service` with `areaServed: City`.
  - Post: `BlogPosting` (author Person, datePublished, dateModified, image).
  - `BreadcrumbList` on all inner pages.
  - `FAQPage` wherever a faq section or attached FAQs render.
  - `AggregateRating` only on `/reviews`, and only from on-site reviews.
- **Sitemap:** `public/sitemap.xml` is regenerated on any publish. It includes all indexable URLs with `lastmod` and excludes noindex pages, `/thank-you` and drafts.
- **robots.txt:** From Settings. The default allows everything, disallows `/admin` and `/forms`, and gives the sitemap URL.
- **Redirects:** Middleware (before routing) checks `redirects` (cached), counts hits, and issues a 301 or 302. Slug changes create redirects automatically.
- **Internal linking:**
  - Services ↔ areas ↔ area×service ↔ posts, through the related blocks.
  - The footer lists the top services and areas.
- **Heading audit:** A Pest test renders every URL and asserts exactly 1 `<h1>`, a non-empty title of 70 characters or less, and a description of 170 characters or less.
  - It also asserts that **titles, descriptions and H1s are unique across all indexable URLs**.
- **Uniqueness in admin:** On save, the SEO panel checks the meta title, the meta description and the resolved H1 against every other indexable URL. A duplicate gets an inline warning with a link to the clashing page.
  - The Missing-SEO report has a "Duplicates" tab.
  - Pages that use only fallback values (no custom title or description) are listed as "needs attention".
- **Noindex safeguards (no accidental noindex):**
  - Default `robots_index = true` everywhere. Only `/thank-you` and admin preview are noindex by default.
  - Switching a page to noindex opens a confirm dialog, and the page shows a red "NOINDEX" badge in its list.
  - Dashboard widget: "Pages hidden from Google (N)".
  - **Environment guard:** a global `X-Robots-Tag: noindex` is sent **only when `APP_ENV` ≠ `production`**, to keep local and staging out of Google. In production it is never sent.
  - The robots.txt editor **blocks saving `Disallow: /`** in production, and always appends the `Sitemap:` line.
  - A Pest test asserts that every system page, service, area, active area×service and published post returns `index,follow` and appears in `sitemap.xml`.
- **HTTPS:**
  - `.htaccess` sends a 301 from `http://` to `https://` and from `www` to the non-`www` host (the canonical host is chosen in Settings).
  - In production, `URL::forceScheme('https')`.
  - Header `Strict-Transport-Security: max-age=31536000; includeSubDomains`.
  - Canonicals, OG URLs, sitemap and schema use absolute `https://` URLs only.
  - A test checks the rendered HTML for `http://` asset URLs (mixed content).
- **LocalBusiness schema:** Use `@type: "MovingCompany"`. It is a schema.org LocalBusiness subtype, so it counts as LocalBusiness for Google.
  - Required properties: `name, url, logo, image, telephone, email, address (PostalAddress), geo, openingHoursSpecification, areaServed (City list), priceRange, sameAs`. Add `hasOfferCatalog` listing the services.
  - Use **one real business address only**. Never invent per-city addresses.
  - Validate with Google's Rich Results Test and validator.schema.org.
- **GA4 & Search Console support:**
  - Settings fields:
    - GA4 Measurement ID, or GTM container ID (when GTM is set, GA4 loads through GTM only, to avoid double counting).
    - Search Console **HTML meta tag** verification.
    - Optional **HTML-file verification upload**, served at the site root.
  - GA4 events pushed to `dataLayer`:
    - `generate_lead` (quote submit), `contact_submit`
    - `click_to_call` (any `tel:` click, with a `location` param: header, sticky_bar, cta, footer)
    - `quote_cta_click`
  - The deploy guide has a Search Console checklist: verify the domain property, submit `sitemap.xml`, and use URL Inspection on the key pages.

---

## 10. Static HTML Publisher (Option A core)

**Goal:** Every public GET URL exists as a prebuilt file, `public/static/{path}/index.html` (home is `public/static/index.html`). Apache serves it **without starting PHP**.

`App\Services\StaticPublisher`
- `build(string $path)`:
  1. Render the page internally: `app()->handle(Request::create($path,'GET'))` with the header `X-Static-Build: 1`.
  2. Minify the HTML safely.
  3. Write it atomically: temp file, then `rename`.
  4. Log the result to `static_builds`.
  5. Only status 200 is written. A 404 or redirect deletes the existing file.
- `buildAll()`: Enumerate every URL with `UrlRegistry` (the same source as the sitemap, plus paginated pages). Dispatch the builds as chunked jobs of 25 pages. Purge orphan files at the end.
- `purge(string $path)`: Deletes the file.
- **Dependency map** (model observers, `saved`, `deleted` and `restored`, dispatch `RebuildPaths`):
  | Change | Rebuild |
  |---|---|
  | Page | that page only (the header and footer are covered by the Menu and Settings rows) |
  | Service | its page, `/services`, `/`, all area×service pages of it, all areas offering it |
  | Area | its page, `/service-areas`, `/`, its area×service pages, neighbor areas |
  | Area×Service row or template | the affected combinations (template → all of them) |
  | Post | the post, `/blog` and its pages, its category and its pages, posts that list it as related (simplest: the latest 10 posts) |
  | Category | the category and its posts, plus `/blog` |
  | Review | `/reviews`, `/`, the linked area and service pages |
  | FAQ | every page it is attached to |
  | Media (alt or replace) | every page using it |
  | **Menu, Settings, Theme, Move Types, Redirects** | **full rebuild** (header, footer and forms appear on every page) |
- Always regenerate `sitemap.xml` after a build batch.
- **Scheduled posts:** A scheduler entry runs every minute. It publishes posts whose `published_at` is due and rebuilds the related paths.
- Admin shows a toast "Site rebuilding… (N pages)" plus the status on the dashboard.

**`public/.htaccess`** (before the Laravel front-controller rules):
```apache
RewriteEngine On
# trailing slash → none (except root)
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.+)/$ /$1 [R=301,L]
# serve static cache for clean GET requests
RewriteCond %{REQUEST_METHOD} GET
RewriteCond %{QUERY_STRING} ^$
RewriteCond %{REQUEST_URI} !^/(admin|forms|storage|build|static)
RewriteCond %{DOCUMENT_ROOT}/static/index.html -f
RewriteRule ^$ static/index.html [L]
RewriteCond %{REQUEST_METHOD} GET
RewriteCond %{QUERY_STRING} ^$
RewriteCond %{REQUEST_URI} !^/(admin|forms|storage|build|static)
RewriteCond %{DOCUMENT_ROOT}/static%{REQUEST_URI}/index.html -f
RewriteRule ^(.*)$ static/$1/index.html [L]
# caching headers
<IfModule mod_headers.c>
  <FilesMatch "\.(css|js|woff2|webp|jpg|png|svg)$">Header set Cache-Control "public, max-age=31536000, immutable"</FilesMatch>
  <FilesMatch "\.html$">Header set Cache-Control "public, max-age=300"</FilesMatch>
</IfModule>
# gzip
<IfModule mod_deflate.c>AddOutputFilterByType DEFLATE text/html text/css application/javascript application/json image/svg+xml application/xml</IfModule>
```
- Also add `public/static/.htaccess` that denies direct listing.
- Add `public/storage/.htaccess` that **disables PHP execution** (`php_flag engine off` plus `RemoveHandler .php`).
- When the static cache is toggled off, or a file is missing, Laravel renders live. The site always works.
- Response headers on SSR pages: `X-Robots-Tag` from the robots settings, and a security header set (X-Content-Type-Options, Referrer-Policy, X-Frame-Options SAMEORIGIN, Permissions-Policy).

---

## 11. Performance & Accessibility Targets

- Lighthouse on mobile: **Performance ≥ 90, SEO = 100, Accessibility ≥ 95, Best Practices ≥ 95** for home, service, area, post and quote.
- **LCP < 2.5 s, CLS < 0.1, INP < 200 ms.**
- No render-blocking JS. Vue islands load as `type="module"` with `defer` and mount on `[data-island]`. The reviews slider uses CSS scroll-snap with only a small JS enhancement.
- GA4/GTM load after interaction or idle (`requestIdleCallback`). The Maps JS loads on focus.
- WCAG 2.1 AA:
  - Color contrast is checked on the theme colors. The admin warns if tertiary-on-white contrast is below 4.5:1 for text.
  - Focus rings, skip link, labeled inputs, `aria-live` errors on forms, and keyboard-operable menus and accordions.

---

### 11.1 Client Launch Checklist (must all pass before go-live)
| # | Client requirement | Where it is implemented | How we verify |
|---|---|---|---|
| 1 | Mobile-friendly & responsive | §7 templates, mobile-first Tailwind, sticky bar | Manual QA at 360/375/768/1024/1280 px. No horizontal scroll. Lighthouse mobile. |
| 2 | Speed & Core Web Vitals | §10 static cache, §11 targets | Lighthouse ≥ 90 mobile. PageSpeed Insights field data after launch. |
| 3 | Unique SEO title, meta description, H1 per important page | §6 SEO tab, §9 uniqueness checks | Heading audit test (uniqueness), Missing-SEO report shows 0 issues |
| 4 | XML sitemap & robots.txt | §9 sitemap / robots | `/sitemap.xml` is valid XML and lists all indexable URLs. robots.txt has the Sitemap line. |
| 5 | Important pages indexable, no accidental noindex | §9 noindex safeguards | Indexability test, dashboard widget = only `/thank-you` |
| 6 | HTTPS enabled | §9 HTTPS, §15 AutoSSL | `http://` → 301 to `https://`, HSTS present, no mixed content |
| 7 | LocalBusiness schema | §9 MovingCompany JSON-LD | Rich Results Test: 0 errors |
| 8 | Call Now & Get a Free Quote on every page | §7 header, sticky bar, CTA banners | A test asserts that every URL contains a `tel:` link and a link to `/get-a-quote` |
| 9 | Contact info & phone clearly visible | §7 top bar, header, footer NAP, contact page | Visual QA. The phone is visible above the fold on mobile and desktop. |
| 10 | GA4 & Search Console support | §6 Settings → Integrations, §9 events | GA4 DebugView shows the events. Search Console verification succeeds. |

## 12. Seed Data (SEO-ready placeholder copy)

Write **original, US-English, SEO-oriented placeholder copy**. No lorem ipsum. Each text is 150–600 words, as appropriate, with sensible H2s, and uses the company name token.

- **Settings:** Company "Lone Star Movers" (placeholder), Dallas address, phone `(214) 555-0100`, theme defaults (§2), and lead recipient `admin@example.com`.
- **Admin user** from `.env` (`ADMIN_EMAIL`, `ADMIN_PASSWORD`). There are no hardcoded credentials.
- **Services (6):** Local Moving, Long-Distance Moving, Residential Moving, Commercial Moving, Packing Services, Storage Services. Each gets a hero, 3–4 sections, 4 FAQs and SEO meta.
- **Areas (3):** Dallas TX, Plano TX, Irving TX. Each has lat/lng, ZIPs, all 6 services, the other two as neighbors, local copy, FAQs and SEO meta.
- **Area×Service:** The default template with tokens. All 18 combinations are active.
- **Move Types (6):** see §6.7.
- **Blog:** Categories Moving Tips, Packing Guides and Moving Cost Guides. 1 author. **9 posts** (3 per category), 800–1200 words each, with H2s, plus 5 tags.
- **Reviews:** 12 reviews across the areas, ratings 4–5, marked as sample content.
- **FAQs:** 15 global FAQs.
- **Pages:** All system pages with their section layouts, plus Privacy and Terms templates (flagged "review with legal").
- **Menus:** Header (About, Services ▾, Service Areas ▾, Reviews, Blog, Contact) and the footer columns.
- **Media:** Generate neutral placeholder images (solid brand colors with an icon, created by GD at seed time) in every preset, so the cropping pipeline is exercised. There are no stock-photo licensing issues.

---

## 13. Project Structure

```
app/
  Http/Controllers/Site/*        (public SSR controllers)
  Http/Controllers/Admin/*       (Inertia controllers, one per module)
  Http/Controllers/FormController.php
  Http/Middleware/{HandleRedirects, StaticBuildContext, SecurityHeaders}
  Http/Requests/{Admin/*, QuoteRequest, ContactRequest}
  Models/*  Models/Concerns/{HasSeo, HasSections, HasFaqs, HasSlugRedirect}
  Observers/*  Jobs/{RebuildPaths, BuildStaticPage, SendLeadNotification}
  Services/{StaticPublisher, UrlRegistry, MediaService, SectionRenderer, TokenReplacer}
  Support/{Seo, Schema, Settings}
  Mail/{LeadReceived, LeadAutoReply, ContactReceived}
config/sections.php  config/media.php (presets)
resources/views/{layouts, components, sections, pages, services, areas, blog, reviews, emails, errors}
resources/js/admin/{pages, components (SectionBuilder, SeoPanel, MediaPicker, Cropper, MenuEditor, RichText), layouts}
resources/js/site/{site.ts, islands/QuoteForm.vue, islands/ContactForm.vue, islands/ReviewsSlider.vue, islands/MobileMenu.vue, lib/places.ts}
database/{migrations, seeders, factories}
docs/{01-requirements-and-feasibility.md, 02-build-prompt.md, deploy-cpanel.md, admin-guide.md}
tests/Feature/*
```

---

## 14. Task Order (build in this sequence; each task ends green)

For every task: implement it, write or extend the Pest tests, run `sail test` and `sail npm run build`, and confirm each acceptance criterion before moving on. All commands go through `sail` (§1.1).

| # | Task | Acceptance criteria |
|---|---|---|
| **T0** | **Laravel Sail env (§1.1):** Vue starter kit created via the Composer container, Sail with PHP 8.3 + MySQL 8.0 + Mailpit + queue + scheduler + `apache` profile, Makefile, `git init`, `.env.example`, remove registration | `sail up -d` starts all services healthy. `http://localhost/admin/login` works, Mailpit opens on `:8025`, and `sail test` passes. |
| **T1** | All migrations, models, traits, factories, base seeders (settings, admin) | `migrate:fresh --seed` runs cleanly |
| **T2** | Admin shell: sidebar layout, dashboard stub, users, profile, **Settings** (all tabs except Turnstile), theme CSS variables output | Changing a color updates the public `<head>` variables |
| **T3** | **Media library + cropper + variant pipeline (GD, WebP)**, `<x-picture>`, usage tracking, svg sanitize | Uploading and cropping in each preset produces correct sizes. A used media item cannot be deleted. |
| **T4** | **SEO panel** component + `Seo` support + fallbacks + SERP preview | Every model form has the SEO tab, and the output tags are correct |
| **T5** | **Section builder** (config-driven forms, drag sort, visibility, duplicate) + `SectionRenderer` + all section Blade components | Every section type renders and validates |
| **T6** | Pages module, system pages, preview, draft/publish | Home is fully editable |
| **T7** | Services + Move Types modules | CRUD, sort, slug-change redirect |
| **T8** | Areas + Area×Service grid, template, overrides, tokens | All 18 combinations render with tokens replaced. An inactive combination returns 404. |
| **T9** | Blog: categories, authors, tags, posts, TipTap, scheduling, TOC, reading time | A scheduled post appears on time |
| **T10** | Reviews, FAQs (bank + attach), Menus editor | Menus render in the header and footer, and the dropdowns work |
| **T11** | Public layout + all templates (§7), breadcrumbs, 404, responsive, mobile sticky bar | Visual QA at 375 / 768 / 1280 px |
| **T12** | **Quote form** (exact fields), **Google Places**, contact form, leads + messages modules, CSV export, emails (queued), thank-you + GA event | A lead is saved, both emails are sent (use Mailpit locally), and the form works without JS |
| **T13** | JSON-LD (MovingCompany / LocalBusiness), sitemap, robots, redirects middleware, missing-SEO and duplicates report, uniqueness checks, noindex safeguards, HTTPS rules and HSTS, GA4 events, Search Console verification | Rich Results Test has no errors. The heading, uniqueness, indexability, CTA-presence and mixed-content tests pass. |
| **T14** | **Static publisher**, URL registry, observers / dependency map, queue jobs, `.htaccess`, rebuild button, build log | Editing a service rebuilds only the mapped paths. On the **`apache` profile (`:8080`)**, the static files are served without PHP. Check this by adding a header `X-Served-By: static` from `public/static/.htaccess` and confirming no Laravel log entry appears. A trailing slash returns 301. |
| **T15** | Performance and a11y pass: critical CSS, lazy Maps/GA, font preload, image audit | Lighthouse targets met (§11) |
| **T16** | Full SEO placeholder content seeders (§12) | The site looks complete after `migrate:fresh --seed` |
| **T17** | `docs/deploy-cpanel.md` + `docs/admin-guide.md` | A fresh cPanel deploy can follow the guide end to end |
| **T18** | **Cloudflare Turnstile (LAST):** Settings keys, widget on the quote and contact forms (lazy-loaded), server-side `siteverify`, graceful skip when no keys are set | A bot POST without a token is rejected while keys are set |

---

## 15. cPanel Deployment Notes (for `deploy-cpanel.md`)

> **Moving from Sail to cPanel.** Docker exists only in development. To produce the deploy package, run on the dev machine:
> - `sail composer install --no-dev --optimize-autoloader`
> - `sail npm run build`
>
> Zip the app, excluding `node_modules`, `.env`, `public/static`, `storage/app/public/media` and the Docker files. Upload it, or deploy with cPanel Git Version Control. Change `.env` from the Sail hosts (`mysql`, `mailpit`) to the cPanel DB (`localhost`) and the real SMTP server. Copy the media separately on first launch.

1. **PHP:** In "Select PHP Version", choose 8.3 and enable `gd, pdo_mysql, mbstring, intl, fileinfo, zip, exif, openssl`.
2. **Code location:** Upload the code **outside** `public_html` (e.g. `~/app`). Then do one of the following:
   - (a) Point the domain's document root at `~/app/public` (preferred).
   - (b) Symlink `public_html` to `~/app/public`.
   - (c) Put a bridging `index.php` and `.htaccess` in `public_html`.
3. **Build:** Run `composer install --no-dev --optimize-autoloader` (cPanel Terminal, or locally and upload `vendor/`). Run `npm run build` **locally** and upload `public/build`.
4. **Configure:** Create the MySQL DB and user in cPanel, fill in `.env`, then run `php artisan key:generate`, `migrate --force`, `db:seed --force`, `storage:link` and `optimize`.
5. **Cron** (cPanel → Cron Jobs, every minute):
   ```
   * * * * * cd ~/app && php artisan schedule:run >> /dev/null 2>&1
   ```
   The scheduler runs `queue:work --stop-when-empty --max-time=50` every minute, the scheduled-post publisher, and a daily backup (DB dump and media zip to `storage/app/backups`, keeping 7).
6. **Permissions and first build:** Make `storage/`, `bootstrap/cache` and `public/static` writable (755/775). Then run `php artisan site:rebuild` for the first static build.
7. **Go live:** Turn on SSL (AutoSSL) and force HTTPS in `.htaccess`. Submit the sitemap in Search Console.

---

## 16. Definition of Done

- [ ] Every item in `01-requirements-and-feasibility.md` §4 is implemented, and every task T0–T18 meets its acceptance criteria.
- [ ] All 10 rows of the **Client Launch Checklist (§11.1)** pass.
- [ ] No public page needs JS to show its content. Every public URL has an editable meta title and description.
- [ ] The quote form has exactly the specified fields. Places autocomplete works, with a plain-text fallback.
- [ ] Every image comes from the server media library through presets with WebP.
- [ ] The static cache serves all pages. Publishing updates the affected pages within about a minute (cron) or instantly (sync for small sets).
- [ ] The site runs on cPanel without Node. Development runs fully in Laravel Sail with one `sail up -d`.
- [ ] There are no mover or marketplace features, no roles and no SMS.
- [ ] Tests are green, Lighthouse targets are met, and the docs are written.
