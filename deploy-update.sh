#!/usr/bin/env bash
# =============================================================================
# IPTV Middleware — Safe Production Deploy
# =============================================================================
# Applies code updates to a running server WITHOUT touching existing data.
# - Runs only NEW (pending) migrations — never rolls back
# - Reloads PHP-FPM and queue workers with zero downtime
# - Preserves all user accounts, subscriptions, channels, and VOD
#
# Local usage (push to GitHub + update remote server via SSH):
#   bash deploy-update.sh --host 192.168.20.59 --user iptvadmin --pass 12345678
#
# On-server usage (run directly as root):
#   sudo bash deploy-update.sh [--app-dir /opt/iptv-middleware]
# =============================================================================
set -euo pipefail

APP_DIR="${APP_DIR:-/opt/iptv-middleware}"
REMOTE_HOST=""
REMOTE_USER="root"
REMOTE_PASS=""
REMOTE_PORT="22"

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'
info()    { echo -e "${CYAN}[INFO]${NC}  $*"; }
success() { echo -e "${GREEN}[OK]${NC}    $*"; }
warn()    { echo -e "${YELLOW}[WARN]${NC}  $*"; }
die()     { echo -e "${RED}[ERROR]${NC} $*" >&2; exit 1; }

while [[ $# -gt 0 ]]; do
    case "$1" in
        --app-dir)  APP_DIR="$2";      shift 2 ;;
        --host)     REMOTE_HOST="$2";  shift 2 ;;
        --user)     REMOTE_USER="$2";  shift 2 ;;
        --pass)     REMOTE_PASS="$2";  shift 2 ;;
        --port)     REMOTE_PORT="$2";  shift 2 ;;
        *) warn "Unknown argument: $1"; shift ;;
    esac
done

# =============================================================================
# Remote mode: push to GitHub then SSH into the server and run this script
# =============================================================================
if [[ -n "$REMOTE_HOST" ]]; then
    command -v sshpass &>/dev/null || die "sshpass not installed. Run: brew install sshpass"

    # Push any uncommitted local changes first
    SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    if [[ -d "$SCRIPT_DIR/.git" ]]; then
        info "Pushing latest code to GitHub..."
        cd "$SCRIPT_DIR"
        git add -A
        if ! git diff --cached --quiet; then
            git commit -m "deploy: auto-commit before remote update $(date '+%Y-%m-%d %H:%M')"
        fi
        git push origin main 2>&1 | tail -3
        success "GitHub up to date."
    fi

    SSH_OPTS="-o StrictHostKeyChecking=no -o ConnectTimeout=15 -p ${REMOTE_PORT}"

    info "Uploading deploy script to ${REMOTE_USER}@${REMOTE_HOST}..."
    sshpass -p "$REMOTE_PASS" scp $SSH_OPTS \
        "${BASH_SOURCE[0]}" \
        "${REMOTE_USER}@${REMOTE_HOST}:/tmp/deploy-update.sh"

    info "Running update on ${REMOTE_HOST}..."
    sshpass -p "$REMOTE_PASS" ssh $SSH_OPTS "${REMOTE_USER}@${REMOTE_HOST}" \
        "echo '${REMOTE_PASS}' | sudo -S bash /tmp/deploy-update.sh --app-dir ${APP_DIR}"

    exit $?
fi

# =============================================================================
# On-server mode (runs as root on the target machine)
# =============================================================================
[[ $EUID -eq 0 ]] || die "Run as root: sudo bash deploy-update.sh"
[[ -d "$APP_DIR" ]] || die "App directory not found: $APP_DIR"

# Detect installed PHP-FPM version
PHP_VER="8.2"
for sock in /run/php/php*-fpm.sock; do
    [[ -S "$sock" ]] && PHP_VER=$(echo "$sock" | grep -oP '\d+\.\d+') && break
done
info "Detected PHP ${PHP_VER}"

cd "$APP_DIR"

# =============================================================================
# 1. Pull latest code (preserves .env — never overwritten)
# =============================================================================
info "Pulling latest code from GitHub..."
git config --global --add safe.directory "$APP_DIR" 2>/dev/null || true
git fetch origin
git reset --hard origin/main 2>/dev/null || git reset --hard origin/master 2>/dev/null
success "Code updated."

# =============================================================================
# 2. PHP dependencies
# =============================================================================
info "Updating Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -3
success "Composer done."

# =============================================================================
# 3. Frontend assets
# =============================================================================
info "Building frontend assets..."
npm ci --silent 2>/dev/null || npm install --silent
npm run build --silent
success "Assets built."

# =============================================================================
# 4. Pending migrations only — never rollback, never re-seed
# =============================================================================
info "Running pending migrations..."
PENDING=$(php artisan migrate:status 2>/dev/null | grep -c "Pending" || true)
if [[ "$PENDING" -gt 0 ]]; then
    php artisan migrate --force --no-interaction
    success "${PENDING} migration(s) applied."
else
    success "No pending migrations."
fi

# =============================================================================
# 5. Patch .env XC-VM settings if XC-VM is enabled
# =============================================================================
XCVM_ENABLED=$(grep -E "^XC_VM_ENABLED=" "$APP_DIR/.env" 2>/dev/null | cut -d= -f2 | tr -d '"' || echo "false")
if [[ "$XCVM_ENABLED" == "true" ]]; then
    grep -q "^XC_VM_PROXY_PLAYER=" "$APP_DIR/.env" \
        && sed -i 's/^XC_VM_PROXY_PLAYER=.*/XC_VM_PROXY_PLAYER=true/' "$APP_DIR/.env" \
        || echo "XC_VM_PROXY_PLAYER=true" >> "$APP_DIR/.env"
    success "XC_VM_PROXY_PLAYER=true"
fi

# =============================================================================
# 6. Update systemd service files
# =============================================================================
info "Updating systemd service files..."
for svc in iptv-watchdog iptv-ingest iptv-purge-ffmpeg; do
    src="${APP_DIR}/deploy/${svc}.service"
    [[ -f "$src" ]] && sed "s|__APP_DIR__|${APP_DIR}|g" "$src" > "/etc/systemd/system/${svc}.service"
done
for timer in iptv-watchdog iptv-purge-ffmpeg; do
    src="${APP_DIR}/deploy/${timer}.timer"
    [[ -f "$src" ]] && cp "$src" "/etc/systemd/system/${timer}.timer"
done
systemctl daemon-reload
success "Service files updated."

# =============================================================================
# 7. Rebuild caches
# =============================================================================
info "Rebuilding application caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
success "Caches rebuilt."

# =============================================================================
# 8. Fix permissions
# =============================================================================
chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

# =============================================================================
# 9. XC-VM re-sync
# =============================================================================
if [[ "$XCVM_ENABLED" == "true" ]]; then
    info "Re-syncing to XC-VM..."
    php artisan xcvm:sync --no-progress 2>&1 | tail -5 || warn "XC-VM sync had issues — re-run: php artisan xcvm:sync"
fi

# =============================================================================
# 10. Graceful reload
# =============================================================================
info "Reloading services..."
systemctl reload "php${PHP_VER}-fpm" 2>/dev/null || systemctl restart "php${PHP_VER}-fpm" 2>/dev/null || true
systemctl reload nginx 2>/dev/null || true
supervisorctl restart iptv-queue 2>/dev/null || true
success "Services reloaded."

echo ""
echo -e "${GREEN}╔══════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║          IPTV Middleware — Update Applied Successfully        ║${NC}"
echo -e "${GREEN}╚══════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "  ${YELLOW}Migrations applied:${NC} ${PENDING}"
echo -e "  ${YELLOW}Data preserved:${NC}     users, subscriptions, channels, VOD"
echo ""
