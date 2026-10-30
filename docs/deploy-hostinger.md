# Deploying to Hostinger (hPanel)

How the site runs on Hostinger shared/cloud hosting (hPanel, LiteSpeed). It follows [deploy-cpanel.md](deploy-cpanel.md) for everything not listed here: the release package, `.env` values, SSL notes, email, backups, Search Console, the launch checklist and troubleshooting.

**Live setup (first deployed 2026-10-30)**

| | |
|---|---|
| Domain | `grosolvemovingnetwork.com` |
| SSH | `<ssh-user>@<server-ip>`, port `<ssh-port>` (hPanel → Advanced → SSH Access) |
| App folder | `~/grosolve` (outside the web root) |
| Web root | `~/domains/grosolvemovingnetwork.com/public_html` → symlink to `~/grosolve/public` (Hostinger's default page is kept in `public_html.old`) |
| Database | `<ssh-user>_grosolve` (user of the same name), MariaDB 11.8, host `localhost` |
| PHP | 8.3 (CLI and web), Composer 2 and Git available, no Node |

---

## Hostinger differences

- **`proc_open`, `exec` and `symlink` are disabled in PHP.**
  - The scheduler therefore runs every task in its own process (`Schedule::call` + `Artisan::call` in `routes/console.php`), never `Schedule::command`. Keep it that way: a `Schedule::command` task would silently never run here. A test enforces this.
  - `php artisan storage:link` fails. Create the link in the shell instead (the shell's `ln` works): `ln -s ../storage/app/public ~/grosolve/public/storage`.
- **Linux is case-sensitive, macOS is not.** A path that works locally can fail here (`seeders/content/` vs the real `seeders/Content/` folder broke the first seed). Match folder names exactly.
- **The document root can't be changed** for the main domain, so `public_html` is replaced by a symlink (option (b) in deploy-cpanel.md §4). LiteSpeed follows it and reads `public/.htaccess` as Apache would, including the static cache rules and the HSTS `expr=` header.
- **SSL is automatic** (Hostinger's CDN, `server: hcdn`). Nothing to turn on.
- **hPanel password rules:** database passwords need an uppercase letter, a lowercase letter and a number, 8+ characters.
- **No `crontab` command over SSH.** Cron jobs are added in hPanel only.

---

## First deploy (what was done)

1. **SSH key:** a key on the deploy machine (`~/.ssh/hostinger_grosolve`), its public half added in hPanel → Advanced → SSH Access → SSH keys. Remove it there to revoke access.
2. **Release:** `./scripts/package-release.sh`, then upload and unpack:

   ```bash
   scp -i ~/.ssh/hostinger_grosolve -P <ssh-port> release/grosolve-*.zip <ssh-user>@<server-ip>:~/
   ssh -i ~/.ssh/hostinger_grosolve -p <ssh-port> <ssh-user>@<server-ip>
   mkdir ~/grosolve && cd ~/grosolve && unzip -q ~/grosolve-*.zip && rm ~/grosolve-*.zip
   mkdir -p storage/app/public/media storage/app/backups storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache public/static
   chmod -R 775 storage bootstrap/cache public/static
   ln -s ../storage/app/public public/storage
   ```

3. **Database:** hPanel → Websites → Dashboard → Databases → Management → create database `grosolve` and user `grosolve` (Hostinger adds the `<ssh-user>_` prefix).
4. **`.env`:** as in deploy-cpanel.md §6, with `DB_DATABASE` / `DB_USERNAME` = `<ssh-user>_grosolve`, `DB_HOST=localhost`, and Hostinger email: `MAIL_HOST=smtp.hostinger.com`, `MAIL_PORT=465`, `MAIL_SCHEME=smtps`. Passwords are typed on the server, never sent around:

   ```bash
   ssh -t -i ~/.ssh/hostinger_grosolve -p <ssh-port> <ssh-user>@<server-ip> nano grosolve/.env
   ```

5. **Install:**

   ```bash
   cd ~/grosolve
   php artisan key:generate --force
   php artisan migrate --force
   php artisan db:seed --force
   php artisan optimize
   php artisan static:build --now
   ```

6. **Point the domain at the app:**

   ```bash
   cd ~/domains/grosolvemovingnetwork.com
   mv public_html public_html.old
   ln -s ~/grosolve/public public_html
   ```

7. **Cron:** hPanel → Advanced → Cron Jobs → Custom, every minute (`* * * * *`):

   ```text
   /usr/bin/php /home/<ssh-user>/grosolve/artisan schedule:run
   ```

8. **Check:** `php artisan deploy:check`. Every ✓ except Turnstile (optional) once cron has run for a minute.

---

## Updating the site

Same as deploy-cpanel.md §16, over SSH:

```bash
# On your computer
./scripts/package-release.sh
scp -i ~/.ssh/hostinger_grosolve -P <ssh-port> release/grosolve-YYYYmmdd-HHMM.zip <ssh-user>@<server-ip>:~/

# On the server
cd ~/grosolve && php artisan down --retry=60
unzip -oq ~/grosolve-YYYYmmdd-HHMM.zip && rm ~/grosolve-YYYYmmdd-HHMM.zip
php artisan migrate --force
php artisan optimize
php artisan queue:restart
php artisan up
php artisan static:build --now
```

The zip never contains `.env`, `storage/` uploads and backups, or `public/static`, so they survive the update. The `public/storage` link is not in the zip either and stays in place.

**Rolling back the domain** (shows Hostinger's default page again):

```bash
cd ~/domains/grosolvemovingnetwork.com && rm public_html && mv public_html.old public_html
```
