#!/usr/bin/env bash
#
# install-plugins.sh — Install Grav plugins + composer dependencies
#
# This script runs INSIDE the Grav container (either during Docker build
# or inside a running container). It does NOT use docker exec.
# Use setup-plugins.sh from the host for local development.
#
# Environment variables:
#   GRAV_ROOT  — Grav document root (default: /app/www/public)

set -euo pipefail

GRAV_ROOT="${GRAV_ROOT:-/app/www/public}"

PLUGINS=(
  admin2
  api
  brevo
  email
  error
  flex-objects
  form
  form-captcha-hcaptcha
  github-markdown-alerts
  login
  problems
  shortcode-core
)

echo "==> Installing Grav plugins via GPM..."

cd "$GRAV_ROOT"

for plugin in "${PLUGINS[@]}"; do
  if [ -d "user/plugins/$plugin" ]; then
    echo "    $plugin — already installed, skipping"
  else
    echo "    $plugin — installing..."
    bin/gpm install "$plugin" --no-interaction 2>&1 | tail -3
  fi
done

echo "==> Installing composer dependencies for signature-attachment plugin..."
if [ -f "user/plugins/signature-attachment/composer.json" ]; then
  cd "$GRAV_ROOT/user/plugins/signature-attachment"
  composer install --no-dev 2>&1 | tail -3
  cd "$GRAV_ROOT"
else
  echo "    signature-attachment — composer.json not found, skipping"
fi

echo "==> Clearing cache..."
bin/grav cache 2>&1 | tail -1

echo "==> Done. Plugins installed:"
ls user/plugins/
