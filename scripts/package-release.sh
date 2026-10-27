#!/usr/bin/env bash
# Build an upload-ready zip for cPanel (docs/deploy-cpanel.md, step 2).
#
#   ./scripts/package-release.sh            -> release/grosolve-YYYYmmdd-HHMM.zip
#
# Runs on the dev machine with Docker. It builds the front end, copies the app to
# a clean folder, installs PHP dependencies there WITHOUT dev packages (your own
# vendor/ is untouched) and zips the result. Not included: .env, node_modules,
# uploaded media, backups, the static cache, logs and Docker files.
set -euo pipefail

cd "$(dirname "$0")/.."
ROOT=$(pwd)
STAMP=$(date +%Y%m%d-%H%M)
WORK="$ROOT/release/build"
ZIP="$ROOT/release/grosolve-$STAMP.zip"

echo "1/4  Building front-end assets (public/build)…"
rm -f public/hot
./vendor/bin/sail npm run build >/dev/null

echo "2/4  Copying the app…"
rm -rf "$WORK" && mkdir -p "$WORK"
rsync -a \
  --exclude='.git/' --exclude='.github/' --exclude='.claude/' --exclude='.idea/' --exclude='.vscode/' \
  --exclude='node_modules/' --exclude='vendor/' --exclude='release/' --exclude='tests/' \
  --exclude='docker/' --exclude='compose.yaml' --exclude='Makefile' --exclude='phpunit.xml' \
  --exclude='.env' --exclude='.env.backup' --exclude='.phpunit.result.cache' \
  --exclude='public/hot' --exclude='public/storage' \
  --exclude='public/static/*' --include='public/static/.htaccess' \
  --exclude='storage/app/public/media/' --exclude='storage/app/backups/' \
  --exclude='storage/logs/*.log' \
  --exclude='storage/framework/cache/data/*' --exclude='storage/framework/sessions/*' \
  --exclude='storage/framework/views/*' --exclude='storage/framework/testing/' \
  --exclude='bootstrap/cache/*.php' \
  ./ "$WORK/"
mkdir -p "$WORK/public/static" && cp public/static/.htaccess "$WORK/public/static/.htaccess"

echo "3/4  Installing production PHP dependencies (composer --no-dev)…"
docker run --rm -v "$WORK":/app -w /app composer:2 \
  composer install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts \
  --ignore-platform-req='ext-*' >/dev/null 2>&1
# package:discover is run on the server by "php artisan optimize".

echo "4/4  Zipping…"
(cd "$WORK" && zip -qr -X "$ZIP" .)
rm -rf "$WORK"

echo "Done: ${ZIP#$ROOT/} ($(du -h "$ZIP" | cut -f1))"
