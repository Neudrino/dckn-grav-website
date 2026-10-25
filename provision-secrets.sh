#!/usr/bin/env bash
#
# provision-secrets.sh — Write plugin secrets into Grav config on the server
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
#   BREVO_API_KEY           -> user/config/plugins/brevo.yaml  api_key
#   BREVO_DEFAULT_LIST_ID   -> user/config/plugins/brevo.yaml  default_list_id
#   SMTP_FROM_EMAIL         -> user/config/plugins/email.yaml  from
#   SMTP_SERVER             -> user/config/plugins/email.yaml  mailer.smtp.server
#   SMTP_PORT               -> user/config/plugins/email.yaml  mailer.smtp.port
#   SMTP_ENCRYPTION         -> user/config/plugins/email.yaml  mailer.smtp.encryption
#   SMTP_USERNAME           -> user/config/plugins/email.yaml  mailer.smtp.user
#   SMTP_PASSWORD           -> user/config/plugins/email.yaml  mailer.smtp.password
#
#   Non-secret keys already present on the server (e.g. email.yaml `to:`,
#   `debug:`) are preserved. Written files get mode 600; the Grav cache is
#   cleared when something changed. A missing BREVO_API_KEY only warns —
#   the deploy continues without the newsletter plugin working.
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

PHP_BIN="${HOME}/.grav-deploy/bin/php"
[ -x "$PHP_BIN" ] || PHP_BIN=php

"$PHP_BIN" -r '
require $argv[1];

use Symfony\Component\Yaml\Yaml;

$map = [
    "BREVO_API_KEY"         => ["brevo.yaml", ["api_key"]],
    "BREVO_DEFAULT_LIST_ID" => ["brevo.yaml", ["default_list_id"]],
    "SMTP_FROM_EMAIL"       => ["email.yaml", ["from"]],
    "SMTP_FROM_NAME"        => ["email.yaml", ["from_name"]],
    "SMTP_SERVER"           => ["email.yaml", ["mailer", "smtp", "server"]],
    "SMTP_PORT"             => ["email.yaml", ["mailer", "smtp", "port"]],
    "SMTP_ENCRYPTION"       => ["email.yaml", ["mailer", "smtp", "encryption"]],
    "SMTP_USERNAME"         => ["email.yaml", ["mailer", "smtp", "user"]],
    "SMTP_PASSWORD"         => ["email.yaml", ["mailer", "smtp", "password"]],
];

$pluginsDir = $argv[2] . "/user/config/plugins";
if (!is_dir($pluginsDir)) {
    mkdir($pluginsDir, 0700, true);
}

$byFile = [];
foreach ($map as $key => [$file, $path]) {
    $value = getenv($key);
    if ($value === false || $value === "") {
        continue;
    }
    $byFile[$file][] = [$path, $value];
}

// With SMTP transport secrets present, guarantee the engine is `smtp` —
// a config left on `engine: none` would make `Email::enabled()` false and
// every form mail silently skip sending.
foreach (["SMTP_SERVER", "SMTP_USERNAME", "SMTP_PASSWORD"] as $k) {
    $v = getenv($k);
    if ($v !== false && $v !== "") {
        $byFile["email.yaml"][] = [["mailer", "engine"], "smtp"];
        break;
    }
}

$changed = [];
foreach ($byFile as $file => $updates) {
    $target = "$pluginsDir/$file";
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
    @chmod($target, 0600);
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
  (cd "${GRAV_ROOT}" && bin/grav clearcache --no-interaction 2>&1 | tail -1) \
    || echo "::warning::provision-secrets: cache clear failed; config still picked up by cache mtime check"
fi
