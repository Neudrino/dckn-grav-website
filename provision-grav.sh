#!/usr/bin/env bash
#
# provision-grav.sh — Ensure the latest Grav core exists at REMOTE_PATH
#
# Runs ON the Netcup server (the deploy workflow scps this file to
# ~/.grav-deploy/ first). Not for local execution.
#
# Mirrors the Docker setup, which always fetches the latest Grav:
#   - no core found -> install the latest grav-admin package from getgrav.org
#   - core found    -> run `bin/gpm selfupgrade`
#
# Grav 2.2+ requires PHP ^8.3 (vendor/composer/platform_check.php).
#
# Environment variables:
#   REMOTE_PATH — Grav document root on the server (required)

set -euo pipefail

: "${REMOTE_PATH:?}"

MIN_PHP_ID=80300
PHP_BIN=""
PHP_ID=0
for c in php /usr/local/php*/bin/php /opt/plesk/php/*/bin/php /usr/bin/php* /usr/local/bin/php*; do
  case "$c" in
    */*) [ -x "$c" ] || continue ;;
    *) command -v "$c" >/dev/null 2>&1 || continue; c="$(command -v "$c")" ;;
  esac
  id="$("$c" -r 'echo PHP_VERSION_ID;' 2>/dev/null)" || continue
  case "$id" in ''|*[!0-9]*) continue ;; esac
  if [ "$id" -gt "$PHP_ID" ]; then PHP_ID="$id"; PHP_BIN="$c"; fi
done
[ -n "$PHP_BIN" ] || { echo "::error::no php binary found on server"; exit 1; }
if [ "$PHP_ID" -lt "$MIN_PHP_ID" ]; then
  echo "::error::Grav requires PHP >= 8.3, newest CLI PHP found is $PHP_BIN (id $PHP_ID)."
  echo "::error::Enable a newer PHP version (Plesk: /opt/plesk/php/8.x/bin/php) and re-run."
  exit 1
fi
echo "Using PHP $PHP_BIN (version id $PHP_ID)"

DEPLOY_BIN="$HOME/.grav-deploy/bin"
mkdir -p "$DEPLOY_BIN" "$REMOTE_PATH"
[ -f "$DEPLOY_BIN/composer.phar" ] || curl -fsSL -o "$DEPLOY_BIN/composer.phar" https://getcomposer.org/download/latest-stable/composer.phar
printf '#!/bin/sh\nexec %s -d memory_limit=1G "$HOME/.grav-deploy/bin/composer.phar" "$@"\n' "$PHP_BIN" > "$DEPLOY_BIN/composer"
printf '#!/bin/sh\nexec %s -d memory_limit=512M "$@"\n' "$PHP_BIN" > "$DEPLOY_BIN/php"
chmod +x "$DEPLOY_BIN/composer" "$DEPLOY_BIN/php"
export PATH="$DEPLOY_BIN:$PATH"

cd "$REMOTE_PATH"
export GRAV_ROOT="$PWD"
if [ -f system/defines.php ]; then
  echo "Grav core present - running gpm selfupgrade"
  bin/gpm selfupgrade -y || echo "::warning::gpm selfupgrade failed (non-fatal)"
else
  echo "No Grav core - installing latest grav-admin"
  curl -fsSL -o grav-admin.zip https://getgrav.org/download/core/grav-admin/latest
  mkdir -p .grav-extract
  unzip -q grav-admin.zip -d .grav-extract
  shopt -s dotglob nullglob
  entries=(.grav-extract/*)
  if [ ${#entries[@]} -eq 1 ] && [ -d "${entries[0]}" ]; then
    mv "${entries[0]}"/* .
    rmdir "${entries[0]}"
  else
    mv .grav-extract/* .
  fi
  rmdir .grav-extract
  rm grav-admin.zip
  test -f system/defines.php
fi
