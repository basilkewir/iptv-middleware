#!/usr/bin/env bash
# =============================================================================
# IPTV Middleware + XC-VM Engine — Bare-Metal Auto-Installer
# =============================================================================
# Installs on Ubuntu 22.04 / 24.04 (no Docker).
# XC-VM is bound to 127.0.0.1 only — never reachable from the internet.
# The middleware (Streambox) is the only public-facing panel.
#
# Usage:
#   sudo bash install.sh [--domain example.com] [--port 25460] [--app-dir /opt/iptv]
# =============================================================================
set -euo pipefail

# ── Defaults ──────────────────────────────────────────────────────────────────
APP_DIR="${APP_DIR:-/opt/iptv-middleware}"
ADMIN_USERNAME="${ADMIN_USERNAME:-admin}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-admin123}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@iptv-middleware.com}"
XCVM_DIR="${XCVM_DIR:-/opt/xcvm}"
XCVM_PORT="${XCVM_PORT:-25462}"          # loopback-only, never exposed
MW_PORT="${MW_PORT:-25460}"              # public middleware port
DOMAIN="${DOMAIN:-}"                     # optional domain for Nginx vhost
DB_NAME="${DB_NAME:-iptv_middleware}"
DB_USER="${DB_USER:-iptv}"
DB_PASS="${DB_PASS:-$(openssl rand -hex 16)}"
XCVM_DB_NAME="${XCVM_DB_NAME:-xcvm}"
XCVM_DB_USER="${XCVM_DB_USER:-xcvm}"
XCVM_DB_PASS="${XCVM_DB_PASS:-$(openssl rand -hex 16)}"
PHP_VER="8.2"
NODE_VER="20"
XCVM_REPO="https://github.com/Vateron-Media/XC_VM.git"
XCVM_ACCESS_CODE="$(openssl rand -hex 8)"
XCVM_API_KEY="$(openssl rand -hex 24)"

# ── Colour helpers ─────────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'
info()    { echo -e "${CYAN}[INFO]${NC}  $*"; }
success() { echo -e "${GREEN}[OK]${NC}    $*"; }
warn()    { echo -e "${YELLOW}[WARN]${NC}  $*"; }
die()     { echo -e "${RED}[ERROR]${NC} $*" >&2; exit 1; }

# ── Argument parsing ───────────────────────────────────────────────────────────
while [[ $# -gt 0 ]]; do
    case "$1" in
        --domain)       DOMAIN="$2";         shift 2 ;;
        --port)         MW_PORT="$2";         shift 2 ;;
        --app-dir)      APP_DIR="$2";         shift 2 ;;
        --admin-user)   ADMIN_USERNAME="$2";  shift 2 ;;
        --admin-pass)   ADMIN_PASSWORD="$2";  shift 2 ;;
        --admin-email)  ADMIN_EMAIL="$2";     shift 2 ;;
        *) warn "Unknown argument: $1"; shift ;;
    esac
done

[[ $EUID -eq 0 ]] || die "Run as root: sudo bash install.sh"
[[ -f /etc/os-release ]] || die "Cannot detect OS."
. /etc/os-release
[[ "$ID" == "ubuntu" ]] || warn "Tested on Ubuntu; proceeding on $ID $VERSION_ID."

# ── Source code must already be present ───────────────────────────────────────
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [[ "$SCRIPT_DIR" != "$APP_DIR" ]]; then
    info "Copying source from $SCRIPT_DIR → $APP_DIR"
    mkdir -p "$APP_DIR"
    rsync -a --exclude='.git' --exclude='vendor' --exclude='node_modules' \
          "$SCRIPT_DIR/" "$APP_DIR/"
fi

# =============================================================================
# 1. System packages
# =============================================================================
info "Updating apt and installing system packages…"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq \
    curl wget git unzip rsync ffmpeg \
    nginx \
    "php${PHP_VER}" "php${PHP_VER}-fpm" "php${PHP_VER}-cli" \
    "php${PHP_VER}-mysql" "php${PHP_VER}-redis" "php${PHP_VER}-mbstring" \
    "php${PHP_VER}-xml" "php${PHP_VER}-curl" "php${PHP_VER}-zip" \
    "php${PHP_VER}-bcmath" "php${PHP_VER}-gd" "php${PHP_VER}-intl" \
    "php${PHP_VER}-pcov" "php${PHP_VER}-imagick" \
    mysql-server redis-server \
    supervisor \
    net-tools iproute2 lsof \
    build-essential

# Node.js
if ! command -v node &>/dev/null || [[ "$(node -v | cut -d. -f1 | tr -d 'v')" -lt "$NODE_VER" ]]; then
    info "Installing Node.js ${NODE_VER}…"
    curl -fsSL "https://deb.nodesource.com/setup_${NODE_VER}.x" | bash -
    apt-get install -y -qq nodejs
fi

# Composer
if ! command -v composer &>/dev/null; then
    info "Installing Composer…"
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

success "System packages installed."

# =============================================================================
# 2. MySQL — databases & users
# =============================================================================
info "Configuring MySQL…"
systemctl enable --now mysql

mysql -u root <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';

CREATE DATABASE IF NOT EXISTS \`${XCVM_DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${XCVM_DB_USER}'@'127.0.0.1' IDENTIFIED BY '${XCVM_DB_PASS}';
GRANT ALL PRIVILEGES ON \`${XCVM_DB_NAME}\`.* TO '${XCVM_DB_USER}'@'127.0.0.1';

FLUSH PRIVILEGES;
SQL
success "MySQL databases ready."

# =============================================================================
# 3. Redis
# =============================================================================
info "Configuring Redis…"
systemctl enable --now redis-server
success "Redis running."

# =============================================================================
# 4. XC-VM Engine (loopback-only)
# =============================================================================
info "Installing XC-VM streaming engine…"

if [[ ! -d "$XCVM_DIR/.git" ]]; then
    git clone --depth=1 "$XCVM_REPO" "$XCVM_DIR"
else
    git -C "$XCVM_DIR" pull --ff-only || true
fi

# XC-VM uses its own PHP web server (built-in or nginx vhost on loopback).
# We create a dedicated Nginx vhost bound to 127.0.0.1:$XCVM_PORT.
XCVM_WEBROOT="$XCVM_DIR/www"
[[ -d "$XCVM_WEBROOT" ]] || XCVM_WEBROOT="$XCVM_DIR/public"
[[ -d "$XCVM_WEBROOT" ]] || XCVM_WEBROOT="$XCVM_DIR"

# XC-VM config (main config file — location varies by version)
XCVM_CFG_CANDIDATES=(
    "$XCVM_DIR/config/config.php"
    "$XCVM_DIR/includes/config.php"
    "$XCVM_DIR/config.php"
)
XCVM_CFG=""
for f in "${XCVM_CFG_CANDIDATES[@]}"; do
    [[ -f "$f" ]] && { XCVM_CFG="$f"; break; }
done

if [[ -n "$XCVM_CFG" ]]; then
    info "Patching XC-VM config: $XCVM_CFG"
    # Patch DB credentials
    sed -i "s/'DB_HOST'[[:space:]]*=>[[:space:]]*'[^']*'/'DB_HOST' => '127.0.0.1'/" "$XCVM_CFG" || true
    sed -i "s/'DB_NAME'[[:space:]]*=>[[:space:]]*'[^']*'/'DB_NAME' => '${XCVM_DB_NAME}'/" "$XCVM_CFG" || true
    sed -i "s/'DB_USER'[[:space:]]*=>[[:space:]]*'[^']*'/'DB_USER' => '${XCVM_DB_USER}'/" "$XCVM_CFG" || true
    sed -i "s/'DB_PASS'[[:space:]]*=>[[:space:]]*'[^']*'/'DB_PASS' => '${XCVM_DB_PASS}'/" "$XCVM_CFG" || true
    # Bind to loopback
    sed -i "s/'SERVER_IP'[[:space:]]*=>[[:space:]]*'[^']*'/'SERVER_IP' => '127.0.0.1'/" "$XCVM_CFG" || true
    sed -i "s/'HTTP_PORT'[[:space:]]*=>[[:space:]]*[0-9]*/'HTTP_PORT' => ${XCVM_PORT}/" "$XCVM_CFG" || true
    # Set access code and API key
    sed -i "s/'ACCESS_CODE'[[:space:]]*=>[[:space:]]*'[^']*'/'ACCESS_CODE' => '${XCVM_ACCESS_CODE}'/" "$XCVM_CFG" || true
    sed -i "s/'API_KEY'[[:space:]]*=>[[:space:]]*'[^']*'/'API_KEY' => '${XCVM_API_KEY}'/" "$XCVM_CFG" || true
else
    warn "XC-VM config file not found — you may need to configure it manually at $XCVM_DIR."
fi

# Run XC-VM installer/migrations if a setup script exists
for setup in "$XCVM_DIR/install.php" "$XCVM_DIR/setup.php" "$XCVM_DIR/scripts/install.php"; do
    if [[ -f "$setup" ]]; then
        info "Running XC-VM setup script: $setup"
        php "$setup" \
            --db-host=127.0.0.1 \
            --db-name="$XCVM_DB_NAME" \
            --db-user="$XCVM_DB_USER" \
            --db-pass="$XCVM_DB_PASS" \
            --access-code="$XCVM_ACCESS_CODE" \
            --api-key="$XCVM_API_KEY" \
            --port="$XCVM_PORT" 2>/dev/null || true
        break
    fi
done

# XC-VM Nginx vhost — loopback only, never reachable from outside
cat > /etc/nginx/sites-available/xcvm <<NGINX
# XC-VM internal engine — loopback only, hidden from internet
server {
    listen 127.0.0.1:${XCVM_PORT};
    server_name 127.0.0.1;
    root ${XCVM_WEBROOT};
    index index.php index.html;

    client_max_body_size 0;
    fastcgi_read_timeout 300s;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/run/php/php${PHP_VER}-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param PATH_INFO \$fastcgi_path_info;
        fastcgi_buffering off;
    }

    # VOD bridge: middleware storage served to XC-VM over loopback
    location /vod_bridge/ {
        alias ${APP_DIR}/storage/app/public/vod/;
        add_header Access-Control-Allow-Origin "http://127.0.0.1";
    }

    location ~ /\.(?!well-known).* { deny all; }
}
NGINX

ln -sf /etc/nginx/sites-available/xcvm /etc/nginx/sites-enabled/xcvm
success "XC-VM vhost configured (loopback:${XCVM_PORT})."

# =============================================================================
# 5. Middleware — PHP dependencies & assets
# =============================================================================
info "Installing middleware PHP dependencies…"
cd "$APP_DIR"
sudo -u www-data composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -5 || \
    composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -5

info "Installing JS dependencies and building assets…"
npm ci --silent 2>/dev/null || npm install --silent
npm run build --silent

success "Middleware dependencies installed."

# =============================================================================
# 6. Middleware .env
# =============================================================================
info "Writing middleware .env…"
APP_KEY="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')"
JWT_SECRET="$(openssl rand -hex 32)"

SERVER_IP="$(hostname -I | awk '{print $1}')"
APP_URL="http://${DOMAIN:-$SERVER_IP}:${MW_PORT}"

cat > "$APP_DIR/.env" <<ENV
APP_NAME="IPTV Middleware"
APP_ENV=production
APP_KEY=${APP_KEY}
APP_DEBUG=false
APP_URL=${APP_URL}

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASS}

BROADCAST_DRIVER=log
CACHE_DRIVER=redis
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=localhost
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@iptv.local"
MAIL_FROM_NAME="IPTV Middleware"

ADMIN_USERNAME=${ADMIN_USERNAME}
ADMIN_PASSWORD=${ADMIN_PASSWORD}
ADMIN_EMAIL=${ADMIN_EMAIL}

JWT_SECRET=${JWT_SECRET}
JWT_TTL=1440

STREAM_SERVER_IP=${SERVER_IP}
STREAM_SERVER_PORT=${MW_PORT}
STREAM_DEFAULT_PASSWORD=12345
STREAM_AUTO_DISCONNECT=120

TMDB_API_KEY=
TMDB_API_URL=https://api.themoviedb.org/3
TMDB_IMAGE_URL=https://image.tmdb.org/t/p
TMDB_LANGUAGE=en-US
TMDB_REGION=US
TMDB_CACHE_TTL=86400

OFFLINE_VIDEO_PATH=${APP_DIR}/storage/app/offline/channel-offline.mp4

# ── XC-VM Engine (loopback, hidden from internet) ──────────────────────────
XC_VM_ENABLED=true
XC_VM_URL=http://127.0.0.1
XC_VM_PORT=${XCVM_PORT}
XC_VM_ACCESS_CODE=${XCVM_ACCESS_CODE}
XC_VM_API_KEY=${XCVM_API_KEY}
XC_VM_TIMEOUT=20
XC_VM_RETRIES=2
XC_VM_LIVE_SYNC=true
XC_VM_FULL_RESYNC_SCHEDULE=everyFiveMinutes
XC_VM_PRUNE_REMOTE=false
XC_VM_START_STREAMS=true
XC_VM_PROXY_PLAYER=true
XC_VM_PROXY_URL=http://127.0.0.1
XC_VM_PROXY_PORT=${XCVM_PORT}
XC_VM_PROXY_TIMEOUT=60
XC_VM_VOD_URL_BASE=http://127.0.0.1:${XCVM_PORT}/vod_bridge
XC_VM_LINE_PASSWORD_SOURCE=m3u_token
XC_VM_LINE_PASSWORD_LENGTH=16
ENV

success ".env written."

# =============================================================================
# 7. Middleware — database migrations & seeding
# =============================================================================
info "Running database migrations…"
cd "$APP_DIR"
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link --force 2>/dev/null || true
php artisan config:cache
php artisan route:cache
php artisan view:cache
success "Database migrated and seeded."

# =============================================================================
# 8. Middleware Nginx vhost (public)
# =============================================================================
info "Writing middleware Nginx vhost…"

SERVER_NAME="${DOMAIN:-_}"

cat > /etc/nginx/sites-available/iptv-middleware <<NGINX
server {
    listen ${MW_PORT};
    listen [::]:${MW_PORT};
    server_name ${SERVER_NAME};
    root ${APP_DIR}/public;
    index index.php;

    charset utf-8;
    client_max_body_size 0;
    fastcgi_read_timeout 300s;

    # HLS segments — served directly, no PHP overhead
    location /hls/ {
        alias ${APP_DIR}/storage/app/streams/hls/;
        add_header Cache-Control "no-cache, no-store, must-revalidate";
        add_header Access-Control-Allow-Origin "*";
        types {
            application/vnd.apple.mpegurl m3u8;
            video/mp2t ts;
        }
    }

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        try_files \$uri /index.php?\$query_string;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/run/php/php${PHP_VER}-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param PATH_INFO \$fastcgi_path_info;
        fastcgi_buffering off;
        fastcgi_connect_timeout 300s;
        fastcgi_send_timeout 300s;
        fastcgi_read_timeout 300s;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
NGINX

ln -sf /etc/nginx/sites-available/iptv-middleware /etc/nginx/sites-enabled/iptv-middleware
rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true

nginx -t
systemctl enable --now nginx
systemctl reload nginx
success "Nginx configured."

# =============================================================================
# 9. PHP-FPM tuning
# =============================================================================
info "Tuning PHP-FPM pool…"
PHP_POOL="/etc/php/${PHP_VER}/fpm/pool.d/www.conf"
sed -i 's/^pm = .*/pm = dynamic/'                    "$PHP_POOL"
sed -i 's/^pm.max_children = .*/pm.max_children = 50/'  "$PHP_POOL"
sed -i 's/^pm.start_servers = .*/pm.start_servers = 5/'  "$PHP_POOL"
sed -i 's/^pm.min_spare_servers = .*/pm.min_spare_servers = 5/' "$PHP_POOL"
sed -i 's/^pm.max_spare_servers = .*/pm.max_spare_servers = 20/' "$PHP_POOL"

PHP_INI="/etc/php/${PHP_VER}/fpm/php.ini"
sed -i 's/^upload_max_filesize = .*/upload_max_filesize = 10G/' "$PHP_INI"
sed -i 's/^post_max_size = .*/post_max_size = 10G/'             "$PHP_INI"
sed -i 's/^memory_limit = .*/memory_limit = 512M/'              "$PHP_INI"
sed -i 's/^max_execution_time = .*/max_execution_time = 300/'   "$PHP_INI"

systemctl enable --now "php${PHP_VER}-fpm"
systemctl reload "php${PHP_VER}-fpm"
success "PHP-FPM tuned."

# =============================================================================
# 10. Supervisor — queue worker + scheduler
# =============================================================================
info "Configuring Supervisor…"

cat > /etc/supervisor/conf.d/iptv-middleware.conf <<SUPERVISOR
[program:iptv-queue]
command=php ${APP_DIR}/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
directory=${APP_DIR}
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=2
redirect_stderr=true
stdout_logfile=${APP_DIR}/storage/logs/queue.log
stdout_logfile_maxbytes=50MB
stdout_logfile_backups=3

[program:iptv-scheduler]
command=bash -c 'while true; do php ${APP_DIR}/artisan schedule:run >> ${APP_DIR}/storage/logs/scheduler.log 2>&1; sleep 60; done'
directory=${APP_DIR}
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=${APP_DIR}/storage/logs/scheduler.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=2
SUPERVISOR

systemctl enable --now supervisor
supervisorctl reread
supervisorctl update
success "Supervisor configured."

# =============================================================================
# 11. Systemd services — XC-VM, watchdog, ingest, push purge
# =============================================================================
info "Installing systemd services…"

# Substitute __APP_DIR__ placeholder in deploy service files before copying
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

# XC-VM systemd service — auto-start the engine, bound to loopback
if [[ -f "${APP_DIR}/deploy/xcvm.service" ]]; then
    sed -e "s|/opt/xcvm|${XCVM_DIR}|g" \
        -e "s|\${XCVM_PORT:-25462}|${XCVM_PORT}|g" \
        "${APP_DIR}/deploy/xcvm.service" > /etc/systemd/system/xcvm.service
else
    # Inline fallback if deploy/xcvm.service is missing
    cat > /etc/systemd/system/xcvm.service <<XCVMSVC
[Unit]
Description=XC-VM Streaming Engine (loopback-only)
After=network.target mysql.service redis-server.service
Wants=mysql.service redis-server.service

[Service]
Type=simple
User=www-data
WorkingDirectory=${XCVM_DIR}
ExecStart=/bin/bash -c 'if [ -f ${XCVM_DIR}/start.sh ]; then exec bash ${XCVM_DIR}/start.sh; else exec php -S 127.0.0.1:${XCVM_PORT} -t ${XCVM_DIR}/www ${XCVM_DIR}/www/index.php; fi'
Restart=always
RestartSec=5
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
XCVMSVC
fi

systemctl daemon-reload

# Start XC-VM first — middleware ingest depends on it
systemctl enable xcvm.service
systemctl start xcvm.service || warn "XC-VM service failed to start — check: journalctl -u xcvm"

for unit in iptv-watchdog.timer iptv-purge-ffmpeg.timer iptv-ingest.service; do
    systemctl enable "$unit" 2>/dev/null && systemctl start "$unit" 2>/dev/null || true
done

success "Systemd services installed."

# =============================================================================
# 12. File permissions
# =============================================================================
info "Setting file permissions…"
chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

# VOD upload directories
mkdir -p "$APP_DIR/storage/app/public/vod"
mkdir -p "$APP_DIR/storage/app/public/episodes"
mkdir -p "$APP_DIR/storage/app/streams/hls"
mkdir -p "$APP_DIR/storage/app/offline"
chown -R www-data:www-data \
    "$APP_DIR/storage/app/public/vod" \
    "$APP_DIR/storage/app/public/episodes" \
    "$APP_DIR/storage/app/streams/hls" \
    "$APP_DIR/storage/app/offline"
chmod -R 775 \
    "$APP_DIR/storage/app/public/vod" \
    "$APP_DIR/storage/app/public/episodes" \
    "$APP_DIR/storage/app/streams/hls" \
    "$APP_DIR/storage/app/offline"

success "Permissions set."

# =============================================================================
# 13. Firewall — block XC-VM port from outside
# =============================================================================
if command -v ufw &>/dev/null; then
    info "Configuring UFW firewall…"
    ufw --force enable 2>/dev/null || true
    ufw allow ssh
    ufw allow "${MW_PORT}/tcp"
    ufw allow 80/tcp
    ufw allow 443/tcp
    # Explicitly deny external access to XC-VM port
    ufw deny "${XCVM_PORT}/tcp" 2>/dev/null || true
    success "UFW: port ${XCVM_PORT} blocked externally, ${MW_PORT} open."
fi

# =============================================================================
# 14. Initial XC-VM data migration
# =============================================================================
info "Migrating all existing middleware data to XC-VM (channels, VOD, users, bouquets)…"
cd "$APP_DIR"
php artisan xcvm:migrate --no-progress 2>&1 | tail -20 || warn "Initial migration had failures — re-run: php artisan xcvm:migrate"

# =============================================================================
# 15. Prepare offline HLS video (if ffmpeg available)
# =============================================================================
if command -v ffmpeg &>/dev/null; then
    info "Preparing offline channel video…"
    php artisan streams:prepare-offline 2>/dev/null || true
fi

# =============================================================================
# 16. Final reload
# =============================================================================
systemctl reload nginx
systemctl reload "php${PHP_VER}-fpm"
supervisorctl restart all 2>/dev/null || true

# =============================================================================
# Summary
# =============================================================================
echo ""
echo -e "${GREEN}╔══════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║          IPTV Middleware + XC-VM — Installation Complete     ║${NC}"
echo -e "${GREEN}╚══════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "  Middleware (Streambox):  ${CYAN}${APP_URL}${NC}"
echo -e "  XC-VM engine:            ${YELLOW}127.0.0.1:${XCVM_PORT} (loopback only — hidden)${NC}"
echo ""
echo -e "  MySQL middleware DB:     ${DB_NAME} / ${DB_USER} / ${DB_PASS}"
echo -e "  MySQL XC-VM DB:          ${XCVM_DB_NAME} / ${XCVM_DB_USER} / ${XCVM_DB_PASS}"
echo ""
echo -e "  XC-VM access code:       ${XCVM_ACCESS_CODE}"
echo -e "  XC-VM API key:           ${XCVM_API_KEY}"
echo ""
echo -e "  ${YELLOW}Save the credentials above — they are not shown again.${NC}"
echo ""
echo -e "  Default admin login:     ${ADMIN_USERNAME} / ${ADMIN_PASSWORD}"
echo ""
echo -e "  Useful commands:"
echo -e "    Migrate all data to XC-VM:  php artisan xcvm:migrate"
echo -e "    Dry-run migration:          php artisan xcvm:migrate --dry-run"
echo -e "    Re-sync channels only:      php artisan xcvm:sync --type=channel"
echo -e "    Sync UDP channels to XC-VM: php artisan xcvm:sync-udp"
echo -e "    Scan multicast:             php artisan channels:scan-multicast udp://@239.0.0.1:1234"
echo ""
echo -e "  Logs:"
echo -e "    Middleware:  ${APP_DIR}/storage/logs/laravel.log"
echo -e "    Queue:       ${APP_DIR}/storage/logs/queue.log"
echo -e "    Nginx:       /var/log/nginx/error.log"
echo ""
