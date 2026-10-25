#
# provision-plugins.sh — Install plugins and composer dependencies
#
# The linuxserver/grav image already bundles quark2 theme + plugins.
# This script only installs the missing plugins (brevo) via GPM and
# runs composer install for signature-attachment.
#
# Use setup-plugins.sh from the host for local development.
#
# Environment variables:
#   GRAV_ROOT  — Grav document root (default: /app/www/public)

set -euo pipefail

# The linuxserver/grav image bundles most plugins listed below;
# those already present (entry file in user/plugins/) are skipped.
# Only the missing plugin (brevo) is installed via GPM.
# quark2 theme is also bundled with the image, so no theme install needed.
PLUGINS=(
  admin2
  api
  brevo
  email
  error
  flex-objects
  form
  gis
  github-markdown-alerts
  login
  problems
  shortcode-core
)
# quark2 is bundled with the image (Docker build) so GPM skips it.
# For local dev (fresh clone), GPM installs it here.
THEMES=(
  quark2
)

GRAV_ROOT="${GRAV_ROOT:-/app/www/public}"
cd "$GRAV_ROOT"
GRAV_ROOT="$PWD"

echo "==> Installing Grav plugins via GPM..."
for plugin in "${PLUGINS[@]}"; do
  if [ -f "user/plugins/$plugin/$plugin.php" ]; then
    echo "    $plugin — already installed, skipping"
  else
    echo "    $plugin — installing..."
    bin/gpm install "$plugin" --no-interaction 2>&1 | tail -3
  fi
done

echo "==> Installing Grav themes via GPM..."
for theme in "${THEMES[@]}"; do
  if [ -f "user/themes/$theme/templates/default.html.twig" ]; then
    echo "    $theme — already installed, skipping"
  else
    echo "    $theme — installing..."
    bin/gpm install "$theme" --all-yes 2>&1 | tail -3
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
echo "==> Themes installed:"
ls user/themes/
