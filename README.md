# Grosolve Moving Network: Website & Admin

SEO-first marketing site for a US moving company.
- **Public site:** Laravel (Blade) renders it on the server and caches it as static HTML.
- **Admin panel:** Vue 3 + Inertia, at `/admin`.

Specs:
- [docs/01-requirements-and-feasibility.md](docs/01-requirements-and-feasibility.md): requirements and decisions
- [docs/02-build-prompt.md](docs/02-build-prompt.md): build spec and task list (T0–T18)
- [docs/deploy-cpanel.md](docs/deploy-cpanel.md): deploying to cPanel, cron, SSL, backups, Search Console
- [docs/deploy-hostinger.md](docs/deploy-hostinger.md): the live Hostinger setup and its differences from cPanel
- [docs/admin-guide.md](docs/admin-guide.md): using the admin day to day

## Stack
- **Backend:** Laravel 13 · PHP 8.3 · MySQL 8.0
- **Admin:** Inertia 3 · Vue 3 · Tailwind CSS 4
- **Development:** Laravel Sail (Docker)
- **Production:** cPanel (Apache, no Docker or Node on the server)

## Requirements
Docker Desktop is the only requirement. You do not need PHP, Composer or MySQL on your machine.

Optional shell alias:
```bash
alias sail='sh $([ -f sail ] && echo sail || echo vendor/bin/sail)'
```

## First-time setup
```bash
cp .env.example .env                       # then set ADMIN_PASSWORD
docker run --rm -v "$PWD":/app -w /app composer:2 composer install
make up                                    # app, mysql, mailpit, queue, scheduler
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail artisan storage:link
./vendor/bin/sail npm install
make dev                                   # Vite with hot reload
```

## URLs
| What | URL |
|---|---|
| Public site | http://localhost |
| Admin | http://localhost/admin (login at `/admin/login`) |
| Mailpit (caught emails) | http://localhost:8025 |
| Apache / `.htaccess` test (`make apache`) | http://localhost:8080 |
| MySQL | `127.0.0.1:3306` (user `sail`, password `password`, DB `movers`) |

## Common commands
| Command | Does |
|---|---|
| `make up` / `make down` | Start / stop all containers |
| `make fresh` | Drop all tables, migrate and seed |
| `make test` | Run the test suite (MySQL `testing` database) |
| `make dev` / `make build` | Vite dev server / production build |
| `make shell` | Shell inside the app container |
| `make apache` | Start the cPanel-like Apache container |
| `make rebuild-static` | Rebuild the static HTML cache now (`artisan static:build --now`) |
| `sail artisan static:build /path …` | Rebuild just these pages |
| `sail artisan static:clear` | Delete all static files (everything renders live) |

## Containers
| Service | Purpose |
|---|---|
| `laravel.test` | PHP 8.3 app and Node 24 for Vite |
| `mysql` | MySQL 8.0, matching the cPanel host |
| `mailpit` | Catches all outgoing mail |
| `queue` | `queue:work`, for lead emails and static rebuilds |
| `scheduler` | `schedule:work`, mirroring the cPanel cron |
| `apache` (profile) | `php:8.3-apache`, for testing `public/.htaccess` |

## Starter content
`db:seed` (and `make fresh`) installs SEO-ready placeholder content once:
- 6 services, 3 cities with all 18 city × service pages, 45 FAQs, 12 sample reviews, every system page, menus, 1 author, 9 blog posts.
- Generated placeholder images (no stock photos).

Copy lives in `database/seeders/Content/*.php`. Re-running never overwrites admin edits.
- `SEED_CONTENT=false` skips it.
- Sample reviews and the Privacy/Terms templates are flagged in the admin and must be replaced or reviewed before launch.

## Static HTML cache
Public pages are pre-rendered to `public/static/{path}/index.html`, and Apache serves them without PHP (see `public/.htaccess`).
- Content changes rebuild the affected pages automatically through the queue.
- Menus, settings and theme changes rebuild the whole site.
- The dashboard shows the status and has a "Rebuild site" button.
- The Sail dev server ignores these files; test them with `make apache` (http://localhost:8080).

## Deployment
Live on Hostinger: see [docs/deploy-hostinger.md](docs/deploy-hostinger.md). For any cPanel host: [docs/deploy-cpanel.md](docs/deploy-cpanel.md).
