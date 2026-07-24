#!/usr/bin/with-contenv bash
# shellcheck shell=bash
#
# 99-secrets.sh — Inject secrets from environment variables into Grav config
#
# This script runs during the linuxserver/grav container init process
# (via /custom-cont-init.d/), after symlinks are set up but before
# nginx/php-fpm start.
#
# Secrets are provided as environment variables (set in SnapDeploy dashboard):
#
#   SMTP_SERVER       — SMTP server hostname
#   SMTP_PORT         — SMTP port (e.g. 587)
#   SMTP_ENCRYPTION   — tls, ssl, or none
#   SMTP_USER          — SMTP username
#   SMTP_PASSWORD      — SMTP password
#   SMTP_FROM_EMAIL    — From email address
#   SMTP_FROM_NAME     — From display name (optional)
#
#   BREVO_API_KEY      — Brevo API key for newsletter
#
#   HCAPTCHA_SITE_KEY   — hCaptcha site key
#   HCAPTCHA_SECRET_KEY — hCaptcha secret key
#
#   GRAV_API_SECRET     — API JWT signing secret (for persistence across restarts)
#   GRAV_SECURITY_SECRET — CSRF nonce signing secret (for persistence)
#
# If GRAV_API_SECRET / GRAV_SECURITY_SECRET are not set, Grav will
# auto-generate them (ephemeral — regenerated on each container restart).

set -e

USER_CONFIG="/config/www/user/config"

# ---------------------------------------------------------------------------
# Email plugin (SMTP)
# ---------------------------------------------------------------------------
if [ -n "${SMTP_SERVER:-}" ]; then
  echo "==> Configuring email plugin (SMTP)..."
  cat > "$USER_CONFIG/plugins/email.yaml" <<EOF
enabled: true
from: "${SMTP_FROM_EMAIL:-}"
from_name: "${SMTP_FROM_NAME:-}"
mailer:
  engine: smtp
  smtp:
    server: "${SMTP_SERVER}"
    port: ${SMTP_PORT:-587}
    encryption: "${SMTP_ENCRYPTION:-tls}"
    user: "${SMTP_USER:-}"
    password: "${SMTP_PASSWORD:-}"
content_type: text/html
debug: false
EOF
  echo "    Email plugin configured for ${SMTP_SERVER}:${SMTP_PORT:-587}"
fi

# ---------------------------------------------------------------------------
# Brevo plugin (newsletter)
# ---------------------------------------------------------------------------
if [ -n "${BREVO_API_KEY:-}" ]; then
  echo "==> Configuring Brevo plugin..."
  cat > "$USER_CONFIG/plugins/brevo.yaml" <<EOF
enabled: true
api_key: "${BREVO_API_KEY}"
default_list_id: "${BREVO_DEFAULT_LIST_ID:-}"
EOF
  echo "    Brevo plugin configured"
fi

# ---------------------------------------------------------------------------
# hCaptcha plugin
# ---------------------------------------------------------------------------
if [ -n "${HCAPTCHA_SITE_KEY:-}" ]; then
  echo "==> Configuring hCaptcha plugin..."
  cat > "$USER_CONFIG/plugins/form-captcha-hcaptcha.yaml" <<EOF
enabled: true
hcaptcha:
  site_key: "${HCAPTCHA_SITE_KEY}"
  secret_key: "${HCAPTCHA_SECRET_KEY:-}"
  theme: "${HCAPTCHA_THEME:-light}"
  size: "${HCAPTCHA_SIZE:-normal}"
EOF
  echo "    hCaptcha plugin configured"
fi

# ---------------------------------------------------------------------------
# API secret (for JWT signing — persists across container restarts)
# ---------------------------------------------------------------------------
if [ -n "${GRAV_API_SECRET:-}" ]; then
  echo "==> Writing API secret..."
  cat > "$USER_CONFIG/plugins/api-private.php" <<EOF
<?php

// Injected from GRAV_API_SECRET environment variable.
// Set this in SnapDeploy to persist API tokens across container restarts.

return '${GRAV_API_SECRET}';
EOF
fi

# ---------------------------------------------------------------------------
# Security secret (for CSRF nonce signing — persists across restarts)
# ---------------------------------------------------------------------------
if [ -n "${GRAV_SECURITY_SECRET:-}" ]; then
  echo "==> Writing security secret..."
  cat > "$USER_CONFIG/security-private.php" <<EOF
<?php

// Injected from GRAV_SECURITY_SECRET environment variable.
// Set this in SnapDeploy to persist CSRF tokens across container restarts.

return '${GRAV_SECURITY_SECRET}';
EOF
fi

echo "==> Secrets injection complete."
