# Deploying to cPanel

This guide takes the site from your development machine to a cPanel host, step by step. It covers the first launch, updates, cron, the queue, the static cache, SSL, email, backups and Google Search Console.

**How it runs in production**
- **No Docker or Node on the server.** The front end is built on your computer and uploaded ready to serve.
- **Pages are pre-built HTML files** in `public/static`. Apache serves them directly without starting PHP. Anything not cached falls back to Laravel, so the site always works.
- **One cron job, every minute, drives everything in the background:**
  - the queue (lead emails, page rebuilds);
  - scheduled blog posts;
  - the nightly backup.

> Commands below use `~/grosolve` as the app folder and `example.com` as the domain. Replace them with yours.

---

## 1. What you need

| | |
|---|---|
| **cPanel features** | Terminal (or SSH access), MySQL Databases, Cron Jobs, MultiPHP Manager / Select PHP Version, SSL/TLS Status (AutoSSL), Email Accounts |
| **PHP** | 8.3 or newer |
| **PHP extensions** | `gd` (with WebP), `pdo_mysql`, `mbstring`, `intl`, `fileinfo`, `zip`, `exif`, `openssl`, `zlib` |
| **Database** | MySQL 8.0 (MariaDB 10.6+ also works) |
| **Disk** | About 150 MB for the app, plus your images and 7 days of backups |
| **On your computer** | This project with Docker (Laravel Sail) running |

No Composer, Git or Node is required on the server.

---

## 2. Build the release package (on your computer)

From the project folder:

```bash
./scripts/package-release.sh
```

This script:
- builds the front end (`public/build`);
- copies the app to a clean folder;
- installs PHP packages without the development tools (your own `vendor/` is untouched);
- writes `release/grosolve-YYYYmmdd-HHMM.zip` (about 20 MB).

The zip **does not include**:
- `.env`
- uploaded media (`storage/app/public/media`)
- backups, logs and the static cache
- Docker files, tests and `node_modules`

Those are created on the server or copied separately.

> **Manual alternative:** run `./vendor/bin/sail npm run build`, then copy the project without the items above. Inside the copy, run `composer install --no-dev --optimize-autoloader`. Never run `--no-dev` in your working project: it removes the test tools.

---

## 3. Create the database

In **cPanel → MySQL Databases**:

1. Create a database, for example `cpuser_grosolve`.
2. Create a user with a strong password, for example `cpuser_grosolve`.
3. Add the user to the database with **ALL PRIVILEGES**.

Keep the three values for `.env`.

---

## 4. Upload the code (outside `public_html`)

1. In **File Manager**, create `~/grosolve` (next to `public_html`, not inside it).
2. Upload the zip into `~/grosolve` and **Extract** it.
3. Point the website at `~/grosolve/public`. Use the first option your host allows:

**(a) Change the document root (preferred).** In **cPanel → Domains**, edit the domain and set its document root to `grosolve/public`.

**(b) Replace `public_html` with a symlink** (Terminal):

```bash
mv ~/public_html ~/public_html.old
ln -s ~/grosolve/public ~/public_html
```

**(c) If neither is possible**, copy the *contents* of `~/grosolve/public` into `~/public_html`. Then edit `~/public_html/index.php` so it finds the app one folder up:

```php
// Replace the three paths that start with __DIR__.'/../' with:
if (file_exists($maintenance = __DIR__.'/../grosolve/storage/framework/maintenance.php')) {
    require $maintenance;
}
require __DIR__.'/../grosolve/vendor/autoload.php';
$app = require_once __DIR__.'/../grosolve/bootstrap/app.php';
```

Then add this line to `~/grosolve/.env`, so the static cache is written where Apache looks for it:

```dotenv
STATIC_PATH=/home/cpuser/public_html/static
```

In step 7, skip `storage:link` and create the image link by hand instead:

```bash
ln -s ~/grosolve/storage/app/public ~/public_html/storage
```

With option (c), copy `public/build` and `public/.htaccess` to `public_html` again after every update.

---

## 5. Select PHP 8.3 and its extensions

In **cPanel → MultiPHP Manager** (or **Select PHP Version**), set the domain to **PHP 8.3**. Then:
- Under **Extensions**, enable `gd`, `pdo_mysql`, `mbstring`, `intl`, `fileinfo`, `zip`, `exif`, `openssl` and `zlib`.
- In **MultiPHP INI Editor** (or **Options**), set these values:

| Setting | Value |
|---|---|
| `memory_limit` | `256M` (image processing needs it) |
| `upload_max_filesize` | `10M` |
| `post_max_size` | `12M` |
| `max_execution_time` | `120` |

The Terminal may use a different PHP than the website. Check with `php -v`. If it is not 8.3, use the full path in every command and in cron, for example `/opt/cpanel/ea-php83/root/usr/bin/php` or `/usr/local/bin/ea-php83`. Ask your host if unsure.

---

## 6. Create `.env`

In `~/grosolve`, create a file named `.env` with the content below and fill in your values:

```dotenv
APP_NAME="Grosolve Moving Network"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://example.com

LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=cpuser_grosolve
DB_USERNAME=cpuser_grosolve
DB_PASSWORD=your-database-password

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database
# No permanent worker on cPanel: cron starts one every minute.
QUEUE_CRON_WORKER=true

MAIL_MAILER=smtp
MAIL_HOST=mail.example.com
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_USERNAME=no-reply@example.com
MAIL_PASSWORD=your-email-password
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME="${APP_NAME}"

# First admin account (created by db:seed). Choose a strong password.
ADMIN_NAME="Site Admin"
ADMIN_EMAIL=you@example.com
ADMIN_PASSWORD=

# Installs the starter content on the first db:seed (set false for an empty site).
SEED_CONTENT=true
```

- **`APP_URL`** must be the final address with `https://` and without a trailing slash.
- **Email:** the SMTP values can also be set later in **Admin → Settings → Email**, which overrides `.env`.
- **Admin password:** if `ADMIN_PASSWORD` is empty, the seeder generates one and prints it once. Copy it straight away.

---

## 7. Install (Terminal)

```bash
cd ~/grosolve
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan optimize
chmod -R 775 storage bootstrap/cache public/static
```

| Command | What it does |
|---|---|
| `key:generate` | Writes `APP_KEY` into `.env`. **Back up `.env` now**: saved secrets cannot be decrypted without this key. |
| `migrate` | Creates the tables. |
| `db:seed` | Creates the admin account, the default settings, the system pages and the starter content. It takes about half a minute while placeholder images are generated, and is safe to run again. |
| `storage:link` | Makes uploaded images reachable at `/storage`. |
| `optimize` | Caches config, routes and views for speed. **Run it again after any change to `.env`.** |

If `storage:link` fails because symlinks are disabled, ask your host to allow them. Otherwise create the link in File Manager: `public/storage` pointing to `../storage/app/public`.

**Moving existing images:** if you built content in development, copy `storage/app/public/media` from your computer to `~/grosolve/storage/app/public/media`. Also import your database (see [Backups](#12-backups-and-restore)) instead of running `db:seed`.

---

## 8. Cron (one line, every minute)

In **cPanel → Cron Jobs**, add a job with **Common Settings: Once Per Minute (`* * * * *`)** and this command:

```text
cd ~/grosolve && php artisan schedule:run >> /dev/null 2>&1
```

Use the full PHP path from step 5 if the Terminal's `php` is not 8.3, for example:

```text
cd ~/grosolve && /opt/cpanel/ea-php83/root/usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Every minute this runs:

| Task | Schedule |
|---|---|
| Queue worker: lead and contact emails, static page rebuilds | every minute, until the queue is empty (max 50 s) |
| Scheduled blog posts go live | every minute |
| Heartbeat for the dashboard's "cron is not running" warning | every minute |
| Backup: database + media zip | daily at 03:30 |
| Old static build records removed | daily |

**Check it:** after two minutes, the admin dashboard no longer shows "The scheduled task (cron) is not running". `php artisan deploy:check` also shows the last run.

---

## 9. Build the static pages

```bash
php artisan static:build --now
```

This writes every public page to `public/static`, about 50 pages with the starter content. From now on:
- **Content changes:** saving in the admin rebuilds the affected pages through the queue, within about a minute.
- **Site-wide changes:** menus, settings, the theme and service or city names rebuild the whole site.
- **Manual rebuild:** use **Dashboard → Rebuild site** at any time.
- **Debugging:** **Settings → Static Cache** can turn the cache off. All files are deleted at once and every page renders live.

---

## 10. SSL, HTTPS and the canonical domain

1. In **cPanel → SSL/TLS Status**, run **AutoSSL** for the domain and `www.` subdomain. Wait until both show a valid certificate.
2. HTTPS is already enforced by `public/.htaccess`:
   - `http://` redirects to `https://`;
   - `www.example.com` redirects to `example.com`;
   - HSTS is sent once the site runs over HTTPS.
3. In **Admin → Settings → SEO**, set **Canonical host** to `example.com`.

**Want the site on `www.` instead?** Edit the "www → bare host" rule in `public/.htaccess` so it adds `www.` (for example `RewriteCond %{HTTP_HOST} !^www\. [NC]` and `RewriteRule ^ https://www.%{HTTP_HOST}%{REQUEST_URI} [L,R=301,NE]`). Then set the canonical host to `www.example.com` and `APP_URL` to match.

**Behind Cloudflare:** set **SSL/TLS → Full (strict)**, never *Flexible*, because *Flexible* causes a redirect loop with the HTTPS rule. Cloudflare's own caching can stay on; pages refresh within 5 minutes (`max-age=300`).

---

## 11. Email

Lead notifications and auto-replies are sent through SMTP.

1. In **cPanel → Email Accounts**, create `no-reply@example.com` (or use your email provider's SMTP).
2. Put the server, port and password in `.env` (step 6) or in **Admin → Settings → Email**.
3. In **Settings → Email**, set the **lead recipients** (who receives quote requests) and send a **test email**.
4. In **cPanel → Email Deliverability**, make sure SPF and DKIM are valid. This keeps lead emails out of spam folders.

Emails are queued, so they leave within a minute through cron. If none arrive, check cron first (step 8).

---

## 12. Backups and restore

Every night at 03:30, two files are written to `~/grosolve/storage/app/backups` (the newest 7 of each are kept):
- `database-YYYY-MM-DD_HHMMSS.sql.gz`: the whole database.
- `media-YYYY-MM-DD_HHMMSS.zip`: every uploaded image.

The folder is never reachable from the web. Download the files regularly with **File Manager** (or use cPanel's own **Backup** feature as well): a backup on the same server does not protect against losing the server.

To make one now:

```bash
php artisan backup:run
```

**Restore the database:**

```bash
gunzip -c storage/app/backups/database-2026-01-01_033000.sql.gz | mysql -u cpuser_grosolve -p cpuser_grosolve
php artisan optimize:clear && php artisan optimize && php artisan static:build --now
```

You can also import the `.sql.gz` file with **phpMyAdmin → Import**.

**Restore the images:** extract the media zip into `~/grosolve/storage/app/public/` (it contains a `media/` folder).

---

## 13. Final check

```bash
php artisan deploy:check
```

It checks:
- PHP and its extensions;
- the `.env` values;
- the database;
- folder permissions;
- the storage link;
- built assets;
- that the Vite dev file (`public/hot`) is absent;
- the `.htaccess` rules;
- cron;
- the static cache;
- failed jobs.

Fix every **✗** before launch, and review every **!** warning.

Then log in at `https://example.com/admin` and work through the [launch checklist](#15-launch-checklist).

---

## 14. Google Search Console checklist

1. **Add the property.** In Search Console, add a **Domain** property for `example.com`. It covers http, https, www and every subdomain.
2. **Verify it.** Use one of these methods:
   - **DNS (Domain property):** add the TXT record Google shows to your DNS zone (cPanel → Zone Editor, or Cloudflare).
   - **HTML tag (URL-prefix property):** paste the `<meta name="google-site-verification" …>` tag, or just its content value, into **Admin → Settings → Integrations → Search Console verification**. Wait a minute for the rebuild, then click **Verify**.
   - **HTML file:** enter the file name Google gives you (for example `google1234abcd5678.html`) in **Settings → Integrations → Search Console HTML file**. The site serves it at the root.
3. **Submit the sitemap.** In **Sitemaps**, submit `https://example.com/sitemap.xml`. It lists every indexable page and updates itself; `/thank-you` and drafts are excluded. Admins can also see it in **SEO Tools → robots.txt & sitemap**.
4. **Check robots.txt.** Open `https://example.com/robots.txt`. It must end with the `Sitemap:` line and must not contain `Disallow: /`; the editor blocks that line on the live site.
5. **Inspect key pages.** Use **URL Inspection → Request indexing** for the home page, each service page, each city page and one blog post.
6. **Test rich results.** Run `https://search.google.com/test/rich-results` on the home page (MovingCompany), a service page (Service + FAQ) and a blog post (BlogPosting). Expect 0 errors.
7. **Check again after a week.** Look at **Pages** (indexing) and **Experience** (Core Web Vitals). The dashboard widget "Pages hidden from Google" should list only `/thank-you`.

**GA4 / Tag Manager:** enter the GA4 Measurement ID **or** the GTM container ID in **Settings → Integrations**. When both are set, GA4 loads through GTM only, so visits are not counted twice. Open GA4 **DebugView** and check these events:
- `generate_lead` (quote form)
- `contact_submit`
- `click_to_call`
- `quote_cta_click`

---

## 14b. Spam protection (Cloudflare Turnstile)

The forms always have a hidden trap field, a minimum fill-in time and a rate limit (5 sends per 10 minutes per visitor). Turnstile adds Cloudflare's invisible human check on top. It's free, and the site doesn't need to be on Cloudflare.

1. In the Cloudflare dashboard, open **Turnstile → Add widget**.
2. Enter a name and your domain (`example.com`). Choose **Managed** mode.
3. Copy the **Site key** and **Secret key** into **Admin → Settings → Integrations**, then save. The site rebuilds, and the check is active once both keys are set.
4. Send a test quote from a phone. Most visitors see nothing; a "Verify you are human" box appears only when Cloudflare is unsure.

| | |
|---|---|
| **When it loads** | Only when someone starts filling in a form, so it doesn't slow down pages. |
| **Visitors without JavaScript** | Can't pass the check. They get a page asking them to turn JavaScript on, with your phone number. |
| **If Cloudflare is down** | Forms keep working on the other checks; the event is logged. Leads are never lost because of Turnstile. |
| **Switching it off** | Clear the site key. |
| **Testing locally** | Cloudflare's test keys work on `localhost`: site key `1x00000000000000000000AA` with secret `1x0000000000000000000000000000000AA` (always passes) or `2x0000000000000000000000000000000AA` (always fails). |

---

## 15. Launch checklist

**In the admin**
- [ ] **Settings → General:** real company name, phone, email, address, hours, USDOT/MC numbers and social links.
- [ ] **Settings → Branding:** logo (light and dark backgrounds) and favicon.
- [ ] **Settings → Email:** lead recipients set and a test email received.
- [ ] **Settings → Integrations:**
  - Google Places API key, restricted to `https://example.com/*` in Google Cloud;
  - GA4 or GTM ID;
  - Search Console verification;
  - Cloudflare Turnstile site and secret keys (see [Spam protection](#14b-spam-protection-cloudflare-turnstile)).
- [ ] **Reviews:** sample reviews replaced with real ones (the yellow banner disappears).
- [ ] **Pages → Privacy Policy / Terms:** reviewed by a lawyer (the "Note" badge explains this), then remove the note text.
- [ ] **Starter copy:** checked for claims you can stand behind, such as "licensed and insured", "since 2015" and opening days.
- [ ] **SEO Tools → SEO report:** 0 pages need attention, 0 duplicates.
- [ ] A test quote sent from the website: the lead appears in **Quote Requests** and both emails arrive.

**Technical** (the client launch checklist, spec §11.1)

| # | Requirement | How to verify |
|---|---|---|
| 1 | Mobile-friendly | Open the site on a phone; no sideways scrolling; Call / Quote bar at the bottom. |
| 2 | Speed (Core Web Vitals) | PageSpeed Insights on the home, a service and a city page: Performance ≥ 90 on mobile. |
| 3 | Unique title, description and H1 | SEO Tools report: 0 issues, 0 duplicates. |
| 4 | Sitemap and robots.txt | `/sitemap.xml` opens and is submitted; `/robots.txt` has the Sitemap line. |
| 5 | No accidental noindex | Dashboard "Pages hidden from Google" shows only `/thank-you`. |
| 6 | HTTPS | `http://example.com` redirects to `https://`; padlock shown; `deploy:check` passes. |
| 7 | LocalBusiness schema | Rich Results Test on the home page: MovingCompany, 0 errors. |
| 8 | Call Now and Get a Free Quote on every page | Header, mobile bar and the closing banner on every page. |
| 9 | Contact info visible | Phone in the header and top bar, address and hours in the footer. |
| 10 | GA4 and Search Console | GA4 DebugView shows the events; Search Console property verified. |

---

## 16. Updating the site later

1. On your computer, build a new package: `./scripts/package-release.sh`.
2. On the server, put the site in maintenance mode:

   ```bash
   cd ~/grosolve && php artisan down --retry=60
   ```

3. Upload the zip and extract it over `~/grosolve`, allowing it to overwrite files. Your `.env`, `storage/` (images, backups, logs) and `public/static` are not in the zip, so they are kept.
4. Finish the update:

   ```bash
   php artisan migrate --force
   php artisan optimize
   php artisan queue:restart
   php artisan up
   php artisan static:build --now
   ```

- **During maintenance:** cached pages keep loading for visitors. Only forms and the admin show "back in a minute", so keep this window short.
- **`queue:restart`** makes the next cron-started worker load the new code.
- **`static:build --now`** refreshes every page with the new templates and assets. It must run **after** `up`, because it refuses to run while the site is down.

**First update after roles were added (T19):** `migrate` makes every existing user an Administrator. Run `php artisan db:seed --class=RoleSeeder --force` once to add the Content Editor, Sales and SEO Manager roles.

**Never** run `db:seed` with `SEED_CONTENT=true` on purpose after launch. It does nothing while services exist, but there is no reason to run it.

---

## 17. Troubleshooting

| Problem | Likely cause and fix |
|---|---|
| **"500 Server Error"** on every page | Check `storage/logs/laravel-*.log`. Usually folder permissions: run `chmod -R 775 storage bootstrap/cache`. Or a missing `APP_KEY`: run `key:generate`, then `optimize`. |
| **Pages without styles** | `public/build` is missing or `public/hot` exists. Upload the release again and delete `public/hot`. |
| **Changes in the admin don't show on the site** | Cron is not running (dashboard warning), so the rebuild job is waiting. Fix cron, or run `php artisan static:build --now`. |
| **Quote emails don't arrive** | Cron is not running, or SMTP is wrong. Send a test from **Settings → Email** and check `php artisan queue:failed`. |
| **Images fail to upload** | `memory_limit` too low, or GD without WebP. Run `deploy:check`. Raise `memory_limit` to 256M. |
| **Redirect loop** | Cloudflare SSL is set to *Flexible*. Change it to *Full (strict)*. |
| **"This page expired"** after a long time on a form | Normal: the visitor reloads and sends it again. If it happens on every submit, check that `APP_URL` matches the real domain. |
| **Changed `.env` but nothing happens** | Run `php artisan optimize` again; the config is cached. |
| **Need everything live for debugging** | **Settings → Static Cache → off**, or `php artisan static:clear`. |
| **Undo the latest update** | Restore the previous zip and the last database backup, then run `php artisan optimize && php artisan static:build --now`. |
