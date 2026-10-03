#!/usr/bin/env bash
set -Eeuo pipefail

REPOSITORY="${UPDATE_REPOSITORY:-https://github.com/basilkewir/iptv-middleware.git}"
BRANCH="${UPDATE_BRANCH:-main}"
APP_DIR="${APP_DIR:-/opt/iptv-middleware}"

usage() {
    cat <<'USAGE'
Update an existing IPTV Middleware installation from GitHub.

Usage:
  sudo bash update.sh [--app-dir /path/to/installed/app]

The script stages the latest main branch in a temporary directory, installs
production Composer/npm dependencies, deploys without replacing .env or
storage/, applies migrations, and reloads PHP-FPM/queue services. It does not
restart channel playout processes.
USAGE
}

while (($#)); do
    case "$1" in
        --app-dir)
            (($# >= 2)) || { echo "Missing value for --app-dir" >&2; exit 2; }
            APP_DIR="$2"
            shift 2
            ;;
        --help|-h)
            usage
            exit 0
            ;;
        *)
            echo "Unknown argument: $1" >&2
            usage >&2
            exit 2
            ;;
    esac
done

fail() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

[[ $EUID -eq 0 ]] || fail "Run this updater as root: sudo bash update.sh --app-dir /path/to/app"
[[ "$APP_DIR" = /* && -d "$APP_DIR" ]] || fail "Installed app directory not found: $APP_DIR"
APP_DIR="$(cd "$APP_DIR" && pwd -P)"

for required in artisan .env vendor/autoload.php storage/app/streams/hls app bootstrap/cache; do
    [[ -e "$APP_DIR/$required" ]] || fail "Not an installed middleware stack (missing $APP_DIR/$required)"
done

command -v systemctl >/dev/null 2>&1 || fail "systemd is required to reload the installed services."
command -v php >/dev/null 2>&1 || fail "PHP CLI is required by the installed middleware stack."

php_version="$(php -r 'echo PHP_MAJOR_VERSION, ".", PHP_MINOR_VERSION;')"
php_major="${php_version%%.*}"
php_minor="${php_version#*.}"
(( php_major > 8 || (php_major == 8 && php_minor >= 1) )) \
    || fail "PHP $php_version is too old; this application requires PHP 8.1 or newer."

mapfile -t fpm_units < <(
    systemctl list-units --type=service --state=active --no-legend 'php*-fpm.service' 2>/dev/null \
        | awk '{print $1}'
)
(( ${#fpm_units[@]} == 1 )) \
    || fail "Expected exactly one active PHP-FPM service; found ${#fpm_units[@]}."
php_fpm_unit="${fpm_units[0]}"

queue_mode=""
queue_unit=""
if systemctl is-active --quiet middleware-queue.service 2>/dev/null; then
    queue_mode="systemd"
    queue_unit="middleware-queue.service"
elif systemctl is-active --quiet supervisor.service 2>/dev/null \
    && command -v supervisorctl >/dev/null 2>&1 \
    && supervisorctl status iptv-queue 2>/dev/null | grep -q 'RUNNING'; then
    queue_mode="supervisor"
else
    fail "Neither middleware-queue.service nor a running Supervisor iptv-queue worker was found."
fi

stage="$(mktemp -d /tmp/iptv-update.XXXXXX)"
cleanup() {
    rm -rf "$stage"
}
trap cleanup EXIT

missing_packages=()
for package_command in git:git composer:composer rsync:rsync curl:curl; do
    command_name="${package_command%%:*}"
    package_name="${package_command#*:}"
    command -v "$command_name" >/dev/null 2>&1 || missing_packages+=("$package_name")
done
if ((${#missing_packages[@]})); then
    command -v apt-get >/dev/null 2>&1 \
        || fail "Missing required commands (${missing_packages[*]}) and apt-get is unavailable."
    printf 'Installing required update tools: %s\n' "${missing_packages[*]}"
    apt-get update
    DEBIAN_FRONTEND=noninteractive apt-get install -y "${missing_packages[@]}"
fi

for command_name in git composer rsync curl; do
    command -v "$command_name" >/dev/null 2>&1 \
        || fail "Required command '$command_name' is still unavailable after prerequisite installation."
done

node_major=0
if command -v node >/dev/null 2>&1; then
    node_major="$(node --version | sed 's/^v//' | cut -d. -f1)"
fi
if ! [[ "$node_major" =~ ^[0-9]+$ ]] || (( node_major < 18 )); then
    printf 'Installing Node.js 20 for the frontend build...\n'
    curl -fsSL https://deb.nodesource.com/setup_20.x -o "$stage/nodesource-setup.sh"
    bash "$stage/nodesource-setup.sh"
    apt-get install -y nodejs
fi
command -v npm >/dev/null 2>&1 || {
    apt-get update
    DEBIAN_FRONTEND=noninteractive apt-get install -y npm
}
command -v npm >/dev/null 2>&1 || fail "npm is unavailable after installing Node.js."

release="$stage/release"
printf 'Fetching %s (%s) for installed app %s\n' "$BRANCH" "$REPOSITORY" "$APP_DIR"
git clone --quiet --depth 1 --branch "$BRANCH" "$REPOSITORY" "$release"
mkdir -p "$release/bootstrap/cache"

printf 'Installing production PHP dependencies...\n'
composer install \
    --working-dir="$release" \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

printf 'Building production frontend assets...\n'
(
    cd "$release"
    npm ci --no-audit --no-fund
    npm run build
)

[[ -f "$release/vendor/autoload.php" ]] || fail "Composer did not produce vendor/autoload.php."
[[ -f "$release/public/build/manifest.json" ]] || fail "Frontend build did not produce its manifest."

printf 'Updating application files (preserving .env and storage)...\n'
rsync -a \
    --exclude='/.env' \
    --exclude='/.env.*' \
    --exclude='/.git' \
    --exclude='/node_modules' \
    --exclude='/storage' \
    --exclude='/public/storage' \
    --exclude='/bootstrap/cache' \
    "$release/" "$APP_DIR/"

# A cached package manifest can reference development-only providers left by a
# previous install. Remove manifests before Artisan boots with --no-dev vendor.
rm -f "$APP_DIR/bootstrap/cache/packages.php" "$APP_DIR/bootstrap/cache/services.php"

cd "$APP_DIR"
php artisan migrate --force --no-interaction
php artisan optimize:clear

if [[ "$queue_mode" == "systemd" ]]; then
    systemctl restart "$queue_unit"
else
    supervisorctl restart iptv-queue
fi
systemctl reload "$php_fpm_unit"

printf 'Update completed. Existing configuration/data were preserved; channel playout was not restarted.\n'
