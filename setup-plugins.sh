#!/usr/bin/env bash
#
# setup-plugins.sh — Install required Grav plugins via GPM
#
# Run this once after a fresh `git clone` + `docker compose up -d`:
#   ./setup-plugins.sh
#
# This installs all plugins the site depends on. Plugin configuration
# (in user/config/plugins/) is already tracked in git and will not be
# overwritten — GPM only installs plugin code, not user config.

set -euo pipefail

CONTAINER="${GRAV_CONTAINER:-grav}"
GRAV_ROOT="/app/www/public"

PLUGINS=(
  admin2
  api
  brevo
  email
  error
  flex-objects
  form
  github-markdown-alerts
  login
  problems
  shortcode-core
)

echo "==> Installing Grav plugins via GPM..."

for plugin in "${PLUGINS[@]}"; do
  if docker exec -w "$GRAV_ROOT" "$CONTAINER" test -d "user/plugins/$plugin"; then
    echo "    $plugin — already installed, skipping"
  else
    echo "    $plugin — installing..."
    docker exec -w "$GRAV_ROOT" "$CONTAINER" bin/gpm install "$plugin" --no-interaction 2>&1 | tail -3
  fi
done

echo "==> Clearing cache..."
docker exec -w "$GRAV_ROOT" "$CONTAINER" bin/grav cache 2>&1 | tail -1

echo "==> Installing composer dependencies for signature-attachment plugin..."
if [ -f "$GRAV_ROOT/user/plugins/signature-attachment/composer.json" ]; then
  docker exec -w "$GRAV_ROOT/user/plugins/signature-attachment" "$CONTAINER" composer install --no-dev 2>&1 | tail -3
else
  echo "    signature-attachment — composer.json not found, skipping"
fi

echo "==> Done. Plugins installed:"
docker exec -w "$GRAV_ROOT" "$CONTAINER" ls user/plugins/
