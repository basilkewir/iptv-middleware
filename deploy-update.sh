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

APP_DIR="${APP_DIR:-/home/kotelhms/middleware}"
REMOTE_HOST=""
REMOTE_USER="root"
REMOTE_PASS=""
REMOTE_PORT="22"
NGINX_PORT="8081"

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
        --nginx-port) NGINX_PORT="$2"; shift 2 ;;
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
    sshpass -p "$REMOTE_PASS" scp -P "${REMOTE_PORT}" -o StrictHostKeyChecking=no -o ConnectTimeout=15 \
        "${BASH_SOURCE[0]}" \
        "${REMOTE_USER}@${REMOTE_HOST}:/tmp/deploy-update.sh"

    info "Running update on ${REMOTE_HOST}..."
    sshpass -p "$REMOTE_PASS" ssh $SSH_OPTS "${REMOTE_USER}@${REMOTE_HOST}" \
        "echo '${REMOTE_PASS}' | sudo -S bash /tmp/deploy-update.sh --app-dir ${APP_DIR} --nginx-port ${NGINX_PORT}"

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

# Detect nginx port from existing vhost if not overridden
if [[ -z "${NGINX_PORT:-}" ]]; then
    NGINX_PORT=$(grep -h 'listen ' /etc/nginx/sites-enabled/* 2>/dev/null | grep -oP '\d+' | head -1)
    NGINX_PORT="${NGINX_PORT:-8081}"
fi

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
# 5. Fix nginx HLS location (ensure alias-based /hls/ block is present)
# =============================================================================
info "Checking nginx HLS config..."
NGINX_VHOST=$(ls /etc/nginx/sites-enabled/middleware /etc/nginx/sites-enabled/iptv-middleware 2>/dev/null | head -1 || true)
if [[ -n "$NGINX_VHOST" ]]; then
    if ! grep -q 'location /hls/' "$NGINX_VHOST"; then
        info "Patching nginx vhost: replacing HLS location with alias block..."
        # Detect PHP-FPM socket path used in this vhost
        FPM_SOCK=$(grep -oP 'unix:/run/php/[^;]+' "$NGINX_VHOST" | head -1 || echo "unix:/run/php/php${PHP_VER}-fpm.sock")
        cat > "$NGINX_VHOST" <<NGINX
server {
    listen ${NGINX_PORT};
    server_name _;
    root ${APP_DIR}/public;
    index index.php;
    charset utf-8;
    client_max_body_size 0;
    client_body_timeout 3600s;
    sendfile on;
    tcp_nopush on;
    keepalive_requests 10000;

    location /hls/ {
        alias ${APP_DIR}/storage/app/streams/hls/;
        add_header Cache-Control "no-cache, no-store, must-revalidate";
        add_header Access-Control-Allow-Origin "*";
        add_header X-Accel-Buffering "no";
        types { application/vnd.apple.mpegurl m3u8; video/mp2t ts; }
        sendfile on;
        tcp_nopush on;
    }

    location /storage/ {
        alias ${APP_DIR}/storage/app/public/;
        expires 7d;
        sendfile on;
    }

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /index.php {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root/index.php;
        fastcgi_param SCRIPT_NAME     /index.php;
        fastcgi_pass  ${FPM_SOCK};
        fastcgi_read_timeout 3600s;
        fastcgi_param HTTP_HOST              \$http_host;
        fastcgi_param HTTP_X_FORWARDED_FOR   \$http_x_forwarded_for;
        fastcgi_param HTTP_X_FORWARDED_PROTO \$http_x_forwarded_proto;
        fastcgi_param HTTP_X_FORWARDED_HOST  \$http_x_forwarded_host;
        fastcgi_param HTTP_X_FORWARDED_PORT  \$http_x_forwarded_port;
        fastcgi_param HTTPS                  \$http_x_forwarded_proto;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
NGINX
        nginx -t && systemctl reload nginx && success "Nginx HLS config patched and reloaded."
    else
        success "Nginx HLS config already correct."
    fi
fi

# =============================================================================
# 6. Update systemd service files
# =============================================================================
info "Updating systemd service files..."

for svc in iptv-watchdog iptv-ingest iptv-purge-ffmpeg; do
    src="${APP_DIR}/deploy/${svc}.service"
    if [[ -f "$src" ]]; then
        # Substituting __APP_DIR__ is mandatory: iptv-*.service are templates.
        sed "s|__APP_DIR__|${APP_DIR}|g" "$src" > "/etc/systemd/system/${svc}.service"
        chmod 644 "/etc/systemd/system/${svc}.service"
    fi
done
for timer in iptv-watchdog iptv-purge-ffmpeg; do
    src="${APP_DIR}/deploy/${timer}.timer"
    if [[ -f "$src" ]]; then
        cp "$src" "/etc/systemd/system/${timer}.timer"
        chmod 644 "/etc/systemd/system/${timer}.timer"
    fi
done

# ── Per-channel playout: template unit + privileged control wrapper ─────────
# The wrapper is code, so it is reinstalled on every deploy; the sudoers rule
# is re-validated so a malformed edit can never survive a deploy.
if [[ -f "${APP_DIR}/deploy/iptv-playout@.service" ]]; then
    sed "s|__APP_DIR__|${APP_DIR}|g" "${APP_DIR}/deploy/iptv-playout@.service" \
        > "/etc/systemd/system/iptv-playout@.service"
    chmod 644 "/etc/systemd/system/iptv-playout@.service"
fi
if [[ -f "${APP_DIR}/deploy/iptv-playout-ctl" ]]; then
    install -o root -g root -m 0755 "${APP_DIR}/deploy/iptv-playout-ctl" /usr/local/sbin/iptv-playout-ctl
fi
if [[ -f "${APP_DIR}/deploy/iptv-playout.sudoers" ]]; then
    install -o root -g root -m 0440 "${APP_DIR}/deploy/iptv-playout.sudoers" /etc/sudoers.d/iptv-playout
    if ! visudo -cf /etc/sudoers.d/iptv-playout >/dev/null 2>&1; then
        rm -f /etc/sudoers.d/iptv-playout
        warn "sudoers.d/iptv-playout failed validation and was removed — playout falls back to setsid"
    fi
fi

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
mkdir -p "$APP_DIR/storage/app/streams/hls"
chown -R www-data:www-data "$APP_DIR/storage/app/streams"

# =============================================================================
# 9. Graceful reload
# =============================================================================
info "Reloading services..."
systemctl reload "php${PHP_VER}-fpm" 2>/dev/null || systemctl restart "php${PHP_VER}-fpm" 2>/dev/null || true
systemctl reload nginx 2>/dev/null || true

# Oneshot units (iptv-watchdog, iptv-ingest, iptv-purge-ffmpeg) need no
# restart — they re-read everything on each run.

# Queue workers are owned by Supervisor: install.sh §10 registers the
# iptv-queue and iptv-scheduler programs. There is no middleware-queue
# systemd unit anywhere in this repository, so the old "systemd takes
# priority over supervisor" branch was dead code.
info "Restarting queue workers..."
if systemctl is-active --quiet supervisor 2>/dev/null; then
    supervisorctl restart iptv-queue 2>/dev/null || warn "supervisorctl iptv-queue restart failed"
fi
success "Services reloaded."

echo ""
echo -e "${GREEN}╔══════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║          IPTV Middleware — Update Applied Successfully        ║${NC}"
echo -e "${GREEN}╚══════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "  ${YELLOW}Migrations applied:${NC} ${PENDING}"
echo -e "  ${YELLOW}Data preserved:${NC}     users, subscriptions, channels, VOD"
echo ""
