#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${1:-}"
RELEASE_DIR="${2:-}"

skip() {
    printf 'SKIP: %s\n' "$*"
    exit 0
}

fail() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

[[ "$(uname -s)" == "Linux" ]] || skip "automatic deployment is supported on Linux servers only"
[[ "$APP_DIR" = /* ]] || skip "target app directory must be an absolute path"
[[ -d "$APP_DIR" ]] || skip "no installed app at $APP_DIR"
[[ -d "$RELEASE_DIR" ]] || fail "release directory is missing"

APP_DIR="$(cd "$APP_DIR" && pwd -P)"
RELEASE_DIR="$(cd "$RELEASE_DIR" && pwd -P)"
[[ "$APP_DIR" != "$RELEASE_DIR" && "$APP_DIR" != "$RELEASE_DIR/"* ]] \
    || fail "release files must not be inside the live app directory"

for required in artisan .env vendor/autoload.php storage/app/streams/hls; do
    [[ -e "$APP_DIR/$required" ]] || skip "installed stack prerequisite is missing: $APP_DIR/$required"
done

[[ -x "$APP_DIR/artisan" || -r "$APP_DIR/artisan" ]] || skip "artisan is not readable"
[[ -w "$APP_DIR" && -w "$APP_DIR/app" && -w "$APP_DIR/bootstrap/cache" \
    && -w "$APP_DIR/storage" ]] \
    || skip "runner cannot write the app, bootstrap cache, and storage as the app deployment user"
[[ -x "$RELEASE_DIR/vendor/autoload.php" || -r "$RELEASE_DIR/vendor/autoload.php" ]] \
    || fail "production Composer dependencies are missing from the release"
[[ -f "$RELEASE_DIR/public/build/manifest.json" ]] \
    || fail "built frontend assets are missing from the release"
command -v php >/dev/null 2>&1 || skip "PHP CLI is not installed"
command -v rsync >/dev/null 2>&1 || skip "rsync is not installed"
command -v systemctl >/dev/null 2>&1 || skip "systemd is not available"

php_version="$(php -r 'echo PHP_MAJOR_VERSION, ".", PHP_MINOR_VERSION;')"
php_major="${php_version%%.*}"
php_minor="${php_version#*.}"
(( php_major > 8 || (php_major == 8 && php_minor >= 1) )) \
    || skip "PHP $php_version is older than the application requirement (8.1)"

mapfile -t fpm_units < <(
    systemctl list-units --type=service --state=active --no-legend 'php*-fpm.service' 2>/dev/null \
        | awk '{print $1}'
)
(( ${#fpm_units[@]} == 1 )) \
    || skip "expected one active PHP-FPM service; found ${#fpm_units[@]}"
php_fpm_unit="${fpm_units[0]}"

queue_mode=""
queue_unit=""
if systemctl is-active --quiet middleware-queue.service 2>/dev/null; then
    queue_mode="systemd"
    queue_unit="middleware-queue.service"
elif systemctl is-active --quiet supervisor.service 2>/dev/null \
    && command -v supervisorctl >/dev/null 2>&1; then
    if (( EUID == 0 )); then
        supervisor_status="$(supervisorctl status iptv-queue 2>/dev/null || true)"
    elif command -v sudo >/dev/null 2>&1; then
        supervisor_status="$(sudo -n supervisorctl status iptv-queue 2>/dev/null || true)"
    else
        supervisor_status=""
    fi
    if grep -q 'RUNNING' <<<"$supervisor_status"; then
        queue_mode="supervisor"
    fi
fi
if [[ -z "$queue_mode" ]]; then
    skip "no active middleware-queue service or running Supervisor iptv-queue worker"
fi

if (( EUID != 0 )); then
    command -v sudo >/dev/null 2>&1 || skip "sudo is required to reload the installed services"
    sudo -n -l systemctl reload "$php_fpm_unit" >/dev/null 2>&1 \
        || skip "runner needs passwordless sudo permission to reload $php_fpm_unit"
    if [[ "$queue_mode" == "systemd" ]]; then
        sudo -n -l systemctl restart "$queue_unit" >/dev/null 2>&1 \
            || skip "runner needs passwordless sudo permission to restart $queue_unit"
    else
        sudo -n -l supervisorctl restart iptv-queue >/dev/null 2>&1 \
            || skip "runner needs passwordless sudo permission to restart Supervisor iptv-queue"
    fi
fi

printf 'Deploying to installed stack: %s (PHP-FPM: %s, queue: %s)\n' \
    "$APP_DIR" "$php_fpm_unit" "$queue_mode"

rsync -a \
    --exclude='/.env' \
    --exclude='/.env.*' \
    --exclude='/.git' \
    --exclude='/node_modules' \
    --exclude='/storage' \
    --exclude='/public/storage' \
    --exclude='/bootstrap/cache' \
    "$RELEASE_DIR/" "$APP_DIR/"

cd "$APP_DIR"
php artisan migrate --force --no-interaction
php artisan optimize:clear

if [[ "$queue_mode" == "systemd" ]]; then
    if (( EUID == 0 )); then
        systemctl restart "$queue_unit"
    else
        sudo -n systemctl restart "$queue_unit"
    fi
else
    if (( EUID == 0 )); then
        supervisorctl restart iptv-queue
    else
        sudo -n supervisorctl restart iptv-queue
    fi
fi

if (( EUID == 0 )); then
    systemctl reload "$php_fpm_unit"
else
    sudo -n systemctl reload "$php_fpm_unit"
fi

printf 'Deployment completed. Playout processes were not restarted.\n'
