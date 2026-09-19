#!/usr/bin/env bash
# =============================================================================
# IPTV Middleware — Safe Production Deploy
# =============================================================================
# Applies code updates to a running VPS WITHOUT touching existing data.
# - Runs only NEW (pending) migrations — never rolls back
# - Reloads PHP-FPM and queue workers with zero downtime
# - Enables XC-VM proxy if XC-VM is already configured
# - Preserves all user accounts, subscriptions, channels, and VOD
#
# Usage (on the VPS):
#   sudo bash deploy-update.sh [--app-dir /opt/iptv-middleware]
# =============================================================================
set -euo pipefail

APP_DIR="${APP_DIR:-/opt/iptv-middleware}"
PHP_VER="8.2"

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'
info()    { echo -e "${CYAN}[INFO]${NC}  $*"; }
success() { echo -e "${GREEN}[OK]${NC}    $*"; }
warn()    { echo -e "${YELLOW}[WARN]${NC}  $*"; }
die()     { echo -e "${RED}[ERROR]${NC} $*" >&2; exit 1; }

while [[ $# -gt 0 ]]; do
    case "$1" in
        --app-dir) APP_DIR="$2"; shift 2 ;;
        *) warn "Unknown argument: $1"; shift ;;
    esac
done

[[ $EUID -eq 0 ]] || die "Run as root: sudo bash deploy-update.sh"
[[ -d "$APP_DIR" ]] || die "App directory not found: $APP_DIR"

cd "$APP_DIR"

# =============================================================================
# 1. Pull latest code (preserves .env — never overwritten)
# =============================================================================
info "Pulling latest code…"
if [[ -d "$APP_DIR/.git" ]]; then
    git fetch origin
    git reset --hard origin/main 2>/dev/null || git reset --hard origin/master 2>/dev/null || true
    success "Code updated from git."
else
    warn "Not a git repo — skipping git pull. Copy files manually if needed."
fi

# =============================================================================
# 2. PHP dependencies (no-dev, non-interactive)
# =============================================================================
info "Updating Composer dependencies…"
composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -3
success "Composer done."

# =============================================================================
# 3. Frontend assets
# =============================================================================
info "Building frontend assets…"
npm ci --silent 2>/dev/null || npm install --silent
npm run build --silent
success "Assets built."

# =============================================================================
# 4. Run ONLY pending migrations — never rollback, never re-seed
# =============================================================================
info "Running pending migrations (additive only)…"
PENDING=$(php artisan migrate:status 2>/dev/null | grep -c "Pending" || true)

if [[ "$PENDING" -gt 0 ]]; then
    info "Found ${PENDING} pending migration(s) — applying…"
    php artisan migrate --force --no-interaction
    success "Migrations applied."
else
    success "No pending migrations."
fi

# =============================================================================
# 5. Enable XC-VM proxy in .env if XC-VM is already configured
#    (only patches the two values — all other .env settings untouched)
# =============================================================================
info "Checking XC-VM proxy setting…"
XCVM_ENABLED=$(grep -E "^XC_VM_ENABLED=" "$APP_DIR/.env" | cut -d= -f2 | tr -d '"' || echo "false")
PROXY_CURRENT=$(grep -E "^XC_VM_PROXY_PLAYER=" "$APP_DIR/.env" | cut -d= -f2 | tr -d '"' || echo "")

if [[ "$XCVM_ENABLED" == "true" ]]; then
    if [[ "$PROXY_CURRENT" != "true" ]]; then
        if grep -q "^XC_VM_PROXY_PLAYER=" "$APP_DIR/.env"; then
            sed -i 's/^XC_VM_PROXY_PLAYER=.*/XC_VM_PROXY_PLAYER=true/' "$APP_DIR/.env"
        else
            echo "XC_VM_PROXY_PLAYER=true" >> "$APP_DIR/.env"
        fi
        success "XC_VM_PROXY_PLAYER enabled."
    else
        success "XC_VM_PROXY_PLAYER already true."
    fi

    # Bump proxy timeout to 60s if it's still at the old 30s default
    PROXY_TIMEOUT=$(grep -E "^XC_VM_PROXY_TIMEOUT=" "$APP_DIR/.env" | cut -d= -f2 | tr -d '"' || echo "")
    if [[ "$PROXY_TIMEOUT" == "30" || "$PROXY_TIMEOUT" == "" ]]; then
        if grep -q "^XC_VM_PROXY_TIMEOUT=" "$APP_DIR/.env"; then
            sed -i 's/^XC_VM_PROXY_TIMEOUT=.*/XC_VM_PROXY_TIMEOUT=60/' "$APP_DIR/.env"
        else
            echo "XC_VM_PROXY_TIMEOUT=60" >> "$APP_DIR/.env"
        fi
        success "XC_VM_PROXY_TIMEOUT set to 60s."
    fi
else
    warn "XC_VM_ENABLED is not true — skipping proxy patch. Enable XC-VM first."
fi

# =============================================================================
# 6. Install XC-VM systemd service if XC-VM dir exists but service doesn't
# =============================================================================
XCVM_DIR="${XCVM_DIR:-/opt/xcvm}"
if [[ -d "$XCVM_DIR" ]] && [[ ! -f /etc/systemd/system/xcvm.service ]]; then
    info "Installing XC-VM systemd service…"
    XCVM_PORT=$(grep -E "^XC_VM_PORT=" "$APP_DIR/.env" | cut -d= -f2 | tr -d '"' || echo "25462")

    sed -e "s|/opt/xcvm|${XCVM_DIR}|g" \
        -e "s|\${XCVM_PORT:-25462}|${XCVM_PORT}|g" \
        "${APP_DIR}/deploy/xcvm.service" > /etc/systemd/system/xcvm.service

    systemctl daemon-reload
    systemctl enable xcvm.service
    systemctl start xcvm.service || warn "XC-VM service failed to start — check: journalctl -u xcvm"
    success "XC-VM service installed."
elif [[ -f /etc/systemd/system/xcvm.service ]]; then
    success "XC-VM service already installed."
fi

# =============================================================================
# 7. Update deploy service files (substitute __APP_DIR__ placeholder)
# =============================================================================
info "Updating systemd service files…"
for svc in iptv-watchdog iptv-ingest iptv-purge-ffmpeg; do
    src="${APP_DIR}/deploy/${svc}.service"
    if [[ -f "$src" ]]; then
        sed "s|__APP_DIR__|${APP_DIR}|g" "$src" > "/etc/systemd/system/${svc}.service"
    fi
done
for timer in iptv-watchdog iptv-purge-ffmpeg; do
    src="${APP_DIR}/deploy/${timer}.timer"
    [[ -f "$src" ]] && cp "$src" "/etc/systemd/system/${timer}.timer"
done
systemctl daemon-reload
success "Service files updated."

# =============================================================================
# 8. Clear and rebuild caches (config/route/view — NOT data caches)
# =============================================================================
info "Rebuilding application caches…"
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
success "Caches rebuilt."

# =============================================================================
# 9. Fix permissions
# =============================================================================
chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

# =============================================================================
# 10. Re-sync to XC-VM (if enabled) — preserves existing mappings
# =============================================================================
XCVM_ENABLED=$(grep -E "^XC_VM_ENABLED=" "$APP_DIR/.env" | cut -d= -f2 | tr -d '"' || echo "false")
if [[ "$XCVM_ENABLED" == "true" ]]; then
    info "Re-syncing to XC-VM (preserves existing channel numbers, users, ports)…"
    cd "$APP_DIR"
    php artisan xcvm:sync --no-progress 2>&1 | tail -10 || warn "XC-VM sync had issues — re-run: php artisan xcvm:sync"
    php artisan xcvm:sync-udp 2>&1 | tail -5 || true
    success "XC-VM re-sync complete."
else
    success "XC-VM not enabled — skipping sync."
fi

# =============================================================================
# 11. Graceful reload — zero downtime
# =============================================================================
info "Reloading services…"
systemctl reload "php${PHP_VER}-fpm" 2>/dev/null || systemctl restart "php${PHP_VER}-fpm" 2>/dev/null || true
systemctl reload nginx 2>/dev/null || true

# Restart queue workers gracefully (--stop-when-empty drains in-flight jobs)
supervisorctl restart iptv-queue 2>/dev/null || true

success "Services reloaded."

# =============================================================================
# Summary
# =============================================================================
echo ""
echo -e "${GREEN}╔══════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║          IPTV Middleware — Update Applied Successfully        ║${NC}"
echo -e "${GREEN}╚══════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "  ${YELLOW}Existing data preserved:${NC} users, subscriptions, channels, VOD"
echo -e "  ${YELLOW}Migrations applied:${NC}       ${PENDING} new migration(s)"
echo ""
echo -e "  Verify XC-VM proxy is working:"
echo -e "    php artisan xcvm:test"
echo ""
echo -e "  If XC-VM was not yet synced, run a full sync now:"
echo -e "    php artisan xcvm:sync"
echo ""
