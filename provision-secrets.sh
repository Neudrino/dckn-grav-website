#!/usr/bin/env bash
#
# provision-secrets.sh — Write deployment-provided values into Grav config
#
# Runs ON the Netcup server (the deploy workflow scps this file to
# ~/.grav-deploy/ first). Not for local execution.
#
# Values arrive on stdin as `KEY=value` lines (one per line, split on the
# first '='), so they never show up in argv or process listings. Keys that
# are empty or absent are skipped — existing server config stays untouched,
# so adding a secret in GitHub and re-deploying provisions it, removing it
# deprovisions nothing by accident.
#
#   BREVO_API_KEY              -> plugins/brevo.yaml  api_key
#   BREVO_DEFAULT_LIST_ID      -> plugins/brevo.yaml  default_list_id
#   BREVO_DOI_ENABLED          -> plugins/brevo.yaml  doi.enabled
#   BREVO_DOI_TEMPLATE_ID      -> plugins/brevo.yaml  doi.template_id
#   BREVO_DOI_REDIRECTION_URL  -> plugins/brevo.yaml  doi.redirection_url
#   SMTP_FROM_EMAIL            -> plugins/email.yaml  from
#   SMTP_SERVER                -> plugins/email.yaml  mailer.smtp.server
#   SMTP_PORT                  -> plugins/email.yaml  mailer.smtp.port
#   SMTP_ENCRYPTION            -> plugins/email.yaml  mailer.smtp.encryption
#   SMTP_USERNAME              -> plugins/email.yaml  mailer.smtp.user
#   SMTP_PASSWORD              -> plugins/email.yaml  mailer.smtp.password
#   SMTP_FROM_NAME             -> plugins/email.yaml  from_name
#   SMTP_TO                    -> plugins/email.yaml  to
#   CUSTOM_BASE_URL            -> system.yaml         custom_base_url
#
# Config paths are relative to `user/config/`. Non-secret keys already
# present on the server (e.g. email.yaml `to:`, `debug:`, system.yaml
# home/theme) are preserved. Plugin config files with secrets get mode
# 600; system.yaml keeps its existing permissions. The Grav cache is
# cleared when something changed. A missing BREVO_API_KEY only warns —
# the deploy continues without the newsletter plugin working.
#
# Environment variables:
#   GRAV_ROOT — Grav document root on the server (required)

set -euo pipefail

: "${GRAV_ROOT:?}"

while IFS= read -r line; do
  [ -n "$line" ] || continue
  case "$line" in
    *=*) key="${line%%=*}" value="${line#*=}";;
    *) echo "provision-secrets: skipping malformed input line" >&2; continue;;
  esac
  [[ "$key" =~ ^[A-Z0-9_]+$ ]] || { echo "provision-secrets: invalid key '$key'" >&2; exit 1; }
  if [ -n "$value" ]; then
    export "$key=$value"
  else
    unset "$key" 2>/dev/null || true
  fi
done

[ -n "${BREVO_API_KEY:-}" ] \
  || echo "::warning::provision-secrets: BREVO_API_KEY is not configured — the deploy continues, but the newsletter forms will not work until it is set"

# Log which keys actually arrived (names only — never values), so a deploy
# log can show whether the workflow's secrets resolved at all.
received=""
for k in BREVO_API_KEY BREVO_DEFAULT_LIST_ID BREVO_DOI_ENABLED BREVO_DOI_TEMPLATE_ID BREVO_DOI_REDIRECTION_URL SMTP_SERVER SMTP_PORT SMTP_ENCRYPTION SMTP_USERNAME SMTP_PASSWORD SMTP_FROM_EMAIL SMTP_FROM_NAME SMTP_TO CUSTOM_BASE_URL; do
  [ -n "${!k:-}" ] && received="$received $k"
done
echo "provision-secrets: keys received:${received:- NONE — check that the secrets exist in the GitHub environment this workflow runs in}"

PHP_BIN="${HOME}/.grav-deploy/bin/php"
[ -x "$PHP_BIN" ] || PHP_BIN=php

"$PHP_BIN" -r '
require $argv[1];

use Symfony\Component\Yaml\Yaml;

$map = [
    "BREVO_API_KEY"             => ["plugins/brevo.yaml", ["api_key"]],
    "BREVO_DEFAULT_LIST_ID"     => ["plugins/brevo.yaml", ["default_list_id"]],
    "BREVO_DOI_ENABLED"         => ["plugins/brevo.yaml", ["doi", "enabled"]],
    "BREVO_DOI_TEMPLATE_ID"     => ["plugins/brevo.yaml", ["doi", "template_id"]],
    "BREVO_DOI_REDIRECTION_URL" => ["plugins/brevo.yaml", ["doi", "redirection_url"]],
    "SMTP_FROM_EMAIL"           => ["plugins/email.yaml", ["from"]],
    "SMTP_FROM_NAME"            => ["plugins/email.yaml", ["from_name"]],
    "SMTP_TO"                   => ["plugins/email.yaml", ["to"]],
    "SMTP_SERVER"               => ["plugins/email.yaml", ["mailer", "smtp", "server"]],
    "SMTP_PORT"                 => ["plugins/email.yaml", ["mailer", "smtp", "port"]],
    "SMTP_ENCRYPTION"           => ["plugins/email.yaml", ["mailer", "smtp", "encryption"]],
    "SMTP_USERNAME"             => ["plugins/email.yaml", ["mailer", "smtp", "user"]],
    "SMTP_PASSWORD"             => ["plugins/email.yaml", ["mailer", "smtp", "password"]],
    "CUSTOM_BASE_URL"           => ["system.yaml", ["custom_base_url"]],
];

$configDir = $argv[2] . "/user/config";

$byFile = [];
foreach ($map as $key => [$file, $path]) {
    $value = getenv($key);
    if ($value === false || $value === "") {
        continue;
    }
    if ($key === "BREVO_DOI_ENABLED") {
        $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
    $byFile[$file][] = [$path, $value];
}

// With SMTP transport values present, force a sending engine: `smtp`
// (STARTTLS, port 587) or — when encryption is `ssl`, i.e. implicit TLS on
// port 465 — `smtps`. A config left on `engine: none` would make
// `Email::enabled()` false and every form mail silently skip sending.
foreach (["SMTP_SERVER", "SMTP_USERNAME", "SMTP_PASSWORD"] as $k) {
    $v = getenv($k);
    if ($v !== false && $v !== "") {
        $engine = strtolower((string) getenv("SMTP_ENCRYPTION")) === "ssl" ? "smtps" : "smtp";
        $byFile["plugins/email.yaml"][] = [["mailer", "engine"], $engine];
        break;
    }
}

$changed = [];
foreach ($byFile as $file => $updates) {
    $target = "$configDir/$file";
    if (!is_dir(dirname($target))) {
        mkdir(dirname($target), 0700, true);
    }
    $data = [];
    if (is_file($target)) {
        try {
            $data = Yaml::parseFile($target);
        } catch (\Throwable $e) {
            fwrite(STDERR, "::error::provision-secrets: could not parse $target: " . $e->getMessage() . "\n");
            exit(1);
        }
        if (!is_array($data)) {
            fwrite(STDERR, "::error::provision-secrets: $file is not a YAML map\n");
            exit(1);
        }
    }
    foreach ($updates as [$path, $value]) {
        $node = &$data;
        foreach (array_slice($path, 0, -1) as $segment) {
            if (!isset($node[$segment]) || !is_array($node[$segment])) {
                $node[$segment] = [];
            }
            $node = &$node[$segment];
        }
        $node[end($path)] = $value;
        unset($node);
    }
    $written = @file_put_contents($target, Yaml::dump($data, 10, 2), LOCK_EX);
    if ($written === false) {
        fwrite(STDERR, "::error::provision-secrets: could not write $target (check file ownership/permissions on the server)\n");
        exit(1);
    }
    echo "provision-secrets: wrote $target (" . $written . " bytes)\n";
    // Mode 600 is for secret-bearing plugin configs only; shared files
    // like system.yaml keep their existing permissions.
    if (str_starts_with($file, "plugins/")) {
        @chmod($target, 0600);
    }
    $changed[] = $file;
}

if ($changed) {
    echo "provision-secrets: updated " . implode(", ", $changed) . "\n";
} else {
    echo "provision-secrets: nothing to provision (no secrets provided)\n";
}
' "${GRAV_ROOT}/vendor/autoload.php" "${GRAV_ROOT}"

if [ -f "${GRAV_ROOT}/bin/grav" ]; then
  # Non-fatal: Grav also invalidates caches by file mtime, so even a failed
  # clear leaves the provisioned config effective on the next request.
  # Run via $PHP_BIN — the bin/grav shebang needs `php` on PATH, which a
  # non-interactive SSH shell does not have (only $HOME/.grav-deploy/bin/php).
  (cd "${GRAV_ROOT}" && "$PHP_BIN" bin/grav clearcache --no-interaction 2>&1 | tail -1) \
    || echo "::warning::provision-secrets: cache clear failed; config still picked up by cache mtime check"
fi
