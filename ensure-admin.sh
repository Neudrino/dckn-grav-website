#!/usr/bin/env bash
#
# ensure-admin.sh — Create/update the Grav admin account on the server
#
# Runs ON the Netcup server (the deploy workflow scps this file to
# ~/.grav-deploy/ first). Not for local execution.
#
# Credentials arrive on stdin (one value per line), so they never show up
# in argv or process listings:
#   line 1: ADMIN_USERNAME   (lowercase [a-z0-9._-], becomes the yaml filename)
#   line 2: ADMIN_PASSWORD   (plaintext, hashed here and discarded)
#   line 3: ADMIN_EMAIL
#
# The account password is managed by the deployment: every run re-writes
# hashed_password, so changing the GitHub secrets rotates the admin login.
# Any other account fields are preserved on update.
#
# Environment variables:
#   GRAV_ROOT — Grav document root on the server (required)

set -euo pipefail

: "${GRAV_ROOT:?}"

read -r ADMIN_USERNAME
read -r ADMIN_PASSWORD
read -r ADMIN_EMAIL

[ -n "$ADMIN_USERNAME" ] && [ -n "$ADMIN_PASSWORD" ] && [ -n "$ADMIN_EMAIL" ] \
  || { echo "::error::ensure-admin: missing credentials on stdin"; exit 1; }

export ADMIN_USERNAME ADMIN_PASSWORD ADMIN_EMAIL

if ! [[ "$ADMIN_USERNAME" =~ ^[a-z0-9._-]{2,64}$ ]]; then
  echo "::error::ensure-admin: ADMIN_USERNAME must match [a-z0-9._-]{2,64}"
  exit 1
fi

PHP_BIN="${HOME}/.grav-deploy/bin/php"
[ -x "$PHP_BIN" ] || PHP_BIN=php

HASH="$("$PHP_BIN" -r 'echo password_hash(getenv("ADMIN_PASSWORD"), PASSWORD_BCRYPT);')"
NOW="$(date +%s)"
ACCOUNT="${GRAV_ROOT}/user/accounts/${ADMIN_USERNAME}.yaml"

if [ -f "$ACCOUNT" ]; then
  "$PHP_BIN" -r '
    [$account, $hash, $email, $now] = array_slice($argv, 1);
    $yaml = file_get_contents($account) ?: exit(1);
    $lines = explode("\n", $yaml);
    $found = false;
    foreach ($lines as $i => $line) {
        if (str_starts_with($line, "hashed_password:")) {
            $lines[$i] = "hashed_password: " . var_export($hash, true);
            $found = true;
        } elseif (str_starts_with($line, "email:")) {
            $lines[$i] = "email: " . var_export($email, true);
        } elseif (str_starts_with($line, "modified:")) {
            $lines[$i] = "modified: " . (int) $now;
        }
    }
    $out = implode("\n", $lines);
    if (!$found) {
        $out = rtrim($out) . "\nhashed_password: " . var_export($hash, true) . "\n";
    }
    file_put_contents($account, $out);
  ' "$ACCOUNT" "$HASH" "$ADMIN_EMAIL" "$NOW"
  echo "Grav admin account '$ADMIN_USERNAME' updated (password rotated from deployment secrets)."
else
  umask 177
  {
    printf 'state: enabled\n'
    printf 'email: %s\n' "$("$PHP_BIN" -r 'echo var_export(getenv("ADMIN_EMAIL"), true);')"
    printf 'fullname: %s\n' "$("$PHP_BIN" -r 'echo var_export(mb_convert_case(strtr(getenv("ADMIN_USERNAME"), ["." => " ", "_" => " "]), MB_CASE_TITLE), true);')"
    printf 'title: Administrator\n'
    printf 'access:\n'
    printf '  site:\n'
    printf '    login: true\n'
    printf '  admin:\n'
    printf '    login: true\n'
    printf '    super: true\n'
    printf "hashed_password: '%s'\n" "$HASH"
    printf 'created: %s\n' "$NOW"
    printf 'modified: %s\n' "$NOW"
    printf 'twofa_enabled: false\n'
    printf "twofa_secret: ''\n"
  } > "$ACCOUNT"
  echo "Grav admin account '$ADMIN_USERNAME' created (from deployment secrets)."
fi
