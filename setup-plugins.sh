#!/usr/bin/env bash
#
# setup-plugins.sh — Install required Grav plugins
#
# Run this once after a fresh `git clone` + `docker compose up -d`:
#   ./setup-plugins.sh
#
# This script copies provision-plugins.sh into the running container
# and executes it there. For Docker builds (SnapDeploy), provision-plugins.sh
# is called directly by the Dockerfile.
#
# Plugin configuration (in user/config/plugins/) is already tracked in
# git and will not be overwritten — GPM only installs plugin code, not
# user config.

set -euo pipefail

CONTAINER="${GRAV_CONTAINER:-grav}"
GRAV_ROOT="/app/www/public"

echo "==> Copying provision-plugins.sh into container..."
docker cp "$(dirname "$0")/provision-plugins.sh" "$CONTAINER:/tmp/provision-plugins.sh"
docker exec -w "$GRAV_ROOT" "$CONTAINER" chmod +x /tmp/provision-plugins.sh

echo "==> Running provision-plugins.sh inside container..."
docker exec -w "$GRAV_ROOT" -e GRAV_ROOT="$GRAV_ROOT" "$CONTAINER" /tmp/provision-plugins.sh

echo "==> Cleaning up..."
docker exec "$CONTAINER" rm -f /tmp/provision-plugins.sh

echo "==> Done."
docker exec -w "$GRAV_ROOT" "$CONTAINER" ls user/plugins/
