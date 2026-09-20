#!/usr/bin/env bash
# =============================================================================
# IPTV Middleware — Bare-Metal Auto-Installer (Standalone, no XC-VM)
# =============================================================================
# Installs on Ubuntu 22.04 / 24.04 (no Docker).
# The middleware handles ALL streaming directly using the XC-VM-style
# split-stream architecture: background FFmpeg + Nginx.
#
# Usage:
#   sudo bash install.sh [--domain example.com] [--port 25460] [--fresh]
#   sudo bash install.sh --fresh    # NEW install — drops and recreates database
#   sudo bash install.sh            # UPDATE — preserves existing database
# =============================================================================
set -euo pipefail

# ── Defaults ──────────────────────────────────────────────────────────────────
APP_DIR="${APP_DIR:-/opt/iptv-middleware}"
ADMIN_USERNAME="${ADMIN_USERNAME:-admin}"
ADMIN_PASSWORD="${ADMIN_PASSWORD:-admin123}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@iptv-middleware.com}"
MW_PORT="${MW_PORT:-25460}"
DOMAIN="${DOMAIN:-}"
DB_NAME="${DB_NAME:-iptv_middleware}"
DB_USER="${DB_USER:-iptv}"
DB_PASS="${DB_PASS:-$(openssl rand -hex 16)}"
PHP_VER="8.3"
NODE_VER="20"
FRESH_INSTALL=false

# ── Colour helpers ─────────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; CYAN='\033[0;36m'; NC='\033[0m'
info()    { echo -e "${CYAN}[INFO]${NC}  $*"; }
success() { echo -e "${GREEN}[OK]${NC}    $*"; }
warn()    { echo -e "${YELLOW}[WARN]${NC}  $*"; }
die()     { echo -e "${RED}[ERROR]${NC} $*" >&2; exit 1; }

# ── Argument parsing ───────────────────────────────────────────────────────────
while [[ $# -gt 0 ]]; do
    case "$1" in
        --domain)           DOMAIN="$2";              shift 2 ;;
        --port)             MW_PORT="$2";              shift 2 ;;
        --app-dir)          APP_DIR="$2";              shift 2 ;;
        --admin-user)       ADMIN_USERNAME="$2";       shift 2 ;;
        --admin-pass)       ADMIN_PASSWORD="$2";       shift 2 ;;
        --admin-email)      ADMIN_EMAIL="$2";          shift 2 ;;
        --fresh)            FRESH_INSTALL=true;        shift ;;
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

# Add Sury PHP repo
if ! grep -r 'packages.sury.org/php' /etc/apt/sources.list.d/ &>/dev/null; then
    apt-get install -y -qq curl ca-certificates
    curl -sSLo /tmp/php.gpg https://packages.sury.org/php/apt.gpg
    gpg --dearmor < /tmp/php.gpg > /usr/share/keyrings/sury-php.gpg
    echo "deb [signed-by=/usr/share/keyrings/sury-php.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" \
        > /etc/apt/sources.list.d/sury-php.list
fi
rm -f /etc/apt/sources.list.d/ondrej-ubuntu-php-*.list 2>/dev/null || true

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
rm -f /etc/mysql/FROZEN
systemctl unmask mysql 2>/dev/null || true
systemctl enable mysql
systemctl start mysql || { journalctl -u mysql --no-pager -n 20; die "MySQL failed to start."; }

if [[ "$FRESH_INSTALL" == "true" ]]; then
    info "Fresh install — dropping and recreating database…"
    mysql -u root <<SQL
DROP DATABASE IF EXISTS \`${DB_NAME}\`;
CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
    success "Database recreated."
else
    info "Update mode — preserving existing database."
    mysql -u root <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
    success "Database ready (preserved)."
fi

# =============================================================================
# 3. Redis
# =============================================================================
info "Configuring Redis…"
systemctl unmask redis-server 2>/dev/null || true
systemctl enable redis-server
systemctl start redis-server || true
success "Redis running."

# =============================================================================
# 4. Middleware — PHP dependencies & assets
# =============================================================================
info "Installing middleware PHP dependencies…"
cd "$APP_DIR"
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -5

info "Installing JS dependencies and building assets…"
npm ci --silent 2>/dev/null || npm install --silent
npm run build --silent

success "Middleware dependencies installed."

# =============================================================================
# 5. Middleware .env
# =============================================================================
info "Writing middleware .env…"

# Only generate new APP_KEY on fresh install
if [[ "$FRESH_INSTALL" == "true" ]] || [[ ! -f "$APP_DIR/.env" ]]; then
    APP_KEY="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')"
else
    APP_KEY="$(grep APP_KEY "$APP_DIR/.env" | cut -d= -f2-)"
    [[ -z "$APP_KEY" ]] && APP_KEY="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')"
fi

JWT_SECRET="$(openssl rand -hex 32)"
SERVER_IP="$(hostname -I | awk '{print $1}')"
APP_URL="http://${DOMAIN:-$SERVER_IP}:${MW_PORT}"

# Preserve existing DB credentials if updating
if [[ "$FRESH_INSTALL" != "true" ]] && [[ -f "$APP_DIR/.env" ]]; then
    DB_PASS="$(grep DB_PASSWORD "$APP_DIR/.env" | cut -d= -f2-)"
    DB_USER="$(grep DB_USERNAME "$APP_DIR/.env" | cut -d= -f2-)"
    DB_NAME="$(grep DB_DATABASE "$APP_DIR/.env" | cut -d= -f2-)"
fi

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

# ── Standalone Streaming (no XC-VM dependency) ──────────────────────────────
XC_VM_HLS_SEGMENT_DURATION=4
XC_VM_HLS_PLAYLIST_SIZE=5
XC_VM_HLS_KEYFRAME_INTERVAL=100
XC_VM_MAX_PLAYLIST_AGE=120
ENV

success ".env written."

# =============================================================================
# 6. Middleware — database migrations & seeding
# =============================================================================
info "Running database migrations…"
cd "$APP_DIR"

if [[ "$FRESH_INSTALL" == "true" ]]; then
    php artisan migrate --force
    php artisan db:seed --force
else
    php artisan migrate --force
fi

php artisan storage:link --force 2>/dev/null || true
php artisan config:cache
php artisan route:cache
php artisan view:cache
success "Database migrated."

# =============================================================================
# 7. Middleware Nginx vhost (public)
# =============================================================================
info "Writing middleware Nginx vhost…"

SERVER_NAME="${DOMAIN:-_}"

# Detect installed PHP-FPM version
if [[ ! -S "/run/php/php${PHP_VER}-fpm.sock" ]]; then
    for sock in /run/php/php*-fpm.sock; do
        [[ -S "$sock" ]] && PHP_VER=$(echo "$sock" | grep -oP '\d+\.\d+') && break
    done
fi
info "Using PHP-FPM socket: /run/php/php${PHP_VER}-fpm.sock"

cat > /etc/nginx/sites-available/iptv-middleware <<NGINX
# IPTV Middleware — standalone streaming (XC-VM-style architecture)
# Segments served directly from RAM-backed tmpfs by nginx.
# PHP-FPM only handles the control plane (auth, playlist redirect).
server {
    listen ${MW_PORT} reuseport;
    listen [::]:${MW_PORT} reuseport;
    server_name ${SERVER_NAME};
    root ${APP_DIR}/public;
    index index.php;
    client_max_body_size 0;

    # Performance: zero-copy segment delivery
    sendfile        on;
    tcp_nopush      on;
    tcp_nodelay     on;
    keepalive_timeout 65;
    keepalive_requests 10000;

    # HLS segments — served directly from tmpfs by nginx.
    # PHP never touches this data; sendfile copies straight to the socket.
    # try_files returns 204 for missing segments (keeps ExoPlayer alive).
    location /hls/ {
        alias ${APP_DIR}/storage/app/streams/hls/;
        add_header Cache-Control "no-cache, no-store, must-revalidate";
        add_header Access-Control-Allow-Origin "*";
        add_header X-Accel-Buffering "no";
        types { application/vnd.apple.mpegurl m3u8; video/mp2t ts; }
        try_files \$uri =204;
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
        fastcgi_pass unix:/run/php/php${PHP_VER}-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_buffering off;
        fastcgi_read_timeout 300s;
        fastcgi_param HTTP_X_FORWARDED_FOR \$http_x_forwarded_for;
        fastcgi_param HTTP_X_FORWARDED_PROTO \$http_x_forwarded_proto;
    }

    location ~ /\.(?!well-known).* { deny all; }
}
NGINX

ln -sf /etc/nginx/sites-available/iptv-middleware /etc/nginx/sites-enabled/iptv-middleware
rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true

# Tune nginx global config for high-concurrency streaming
sed -i 's/^worker_processes.*/worker_processes auto;/' /etc/nginx/nginx.conf
grep -q 'worker_rlimit_nofile' /etc/nginx/nginx.conf || \
    sed -i '/^worker_processes/a worker_rlimit_nofile 1048576;' /etc/nginx/nginx.conf
sed -i 's/worker_connections.*/worker_connections 50000;/' /etc/nginx/nginx.conf
grep -q 'use epoll' /etc/nginx/nginx.conf || \
    sed -i '/worker_connections/a \    use epoll;\n    multi_accept on;' /etc/nginx/nginx.conf

# HLS segment cache in RAM + high-performance file serving tuning
cat > /etc/nginx/conf.d/iptv-cache.conf <<'CACHECONF'
proxy_cache_path /dev/shm/nginx_hls_cache levels=1:2
    keys_zone=HLS_CACHE:64m max_size=1g inactive=30s use_temp_path=off;

# Open file descriptor cache — critical for high-volume HLS segment serving.
# Caches stat() results so Nginx doesn't re-stat the same .ts files on
# every concurrent request from hundreds of players.
open_file_cache max=10000 inactive=20s;
open_file_cache_valid 30s;
open_file_cache_min_uses 2;
open_file_cache_errors on;

# Maximize output buffers for high-bitrate video delivery.
# 128k per buffer absorbs full MPEG-TS packets without fragmentation.
output_buffers 1 128k;
postpone_output 1460;
CACHECONF

nginx -t
systemctl enable --now nginx
systemctl reload nginx
success "Nginx configured."

# =============================================================================
# 8. Kernel network tuning (BBR + high-throughput socket buffers)
# =============================================================================
info "Applying kernel network tuning…"
cat > /etc/sysctl.d/99-iptv-streaming.conf <<'SYSCTL'
net.core.default_qdisc = fq
net.ipv4.tcp_congestion_control = bbr
net.core.rmem_max = 134217728
net.core.wmem_max = 134217728
net.ipv4.tcp_rmem = 4096 32768 16777216
net.ipv4.tcp_wmem = 4096 32768 16777216
net.core.rmem_default = 33554432
fs.file-max = 2097152
net.ipv4.tcp_fastopen = 3
net.ipv4.tcp_slow_start_after_idle = 0
net.ipv4.tcp_keepalive_time = 60
net.ipv4.tcp_keepalive_intvl = 10
net.ipv4.tcp_keepalive_probes = 6
SYSCTL
sysctl -p /etc/sysctl.d/99-iptv-streaming.conf 2>/dev/null || true

cat > /etc/security/limits.d/iptv-streaming.conf <<'LIMITS'
www-data soft nofile 1048576
www-data hard nofile 1048576
root     soft nofile 1048576
root     hard nofile 1048576
LIMITS
success "Kernel tuning applied."

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
# 11. Systemd services — watchdog, ingest
# =============================================================================
info "Installing systemd services…"

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
# 13. RAM-backed HLS segment cache (tmpfs)
# =============================================================================
info "Mounting tmpfs for HLS segment cache…"
TOTAL_RAM_KB=$(grep MemTotal /proc/meminfo | awk '{print $2}')
TMPFS_SIZE=$(( TOTAL_RAM_KB / 5 / 1024 ))  # 20% in MB
[[ $TMPFS_SIZE -lt 512 ]] && TMPFS_SIZE=512
HLS_DIR="$APP_DIR/storage/app/streams/hls"

if ! grep -q "$HLS_DIR" /etc/fstab; then
    echo "tmpfs $HLS_DIR tmpfs defaults,size=${TMPFS_SIZE}M,uid=www-data,gid=www-data,mode=0775 0 0" >> /etc/fstab
    mount "$HLS_DIR" 2>/dev/null || true
    success "tmpfs mounted at $HLS_DIR (${TMPFS_SIZE}MB RAM cache)."
else
    success "tmpfs already configured for $HLS_DIR."
fi

# =============================================================================
# 14. Firewall
# =============================================================================
if command -v ufw &>/dev/null; then
    info "Configuring UFW firewall…"
    ufw --force enable 2>/dev/null || true
    ufw allow ssh
    ufw allow "${MW_PORT}/tcp"
    ufw allow 80/tcp
    ufw allow 443/tcp
    success "UFW: port ${MW_PORT} open."
fi

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
echo -e "${GREEN}║       IPTV Middleware — Installation Complete (Standalone)  ║${NC}"
echo -e "${GREEN}╚══════════════════════════════════════════════════════════════╝${NC}"
echo ""
echo -e "  Streambox panel:  ${CYAN}${APP_URL}${NC}"
echo ""
echo -e "  MySQL DB:         ${DB_NAME} / ${DB_USER} / ${DB_PASS}"
echo ""
echo -e "  ${YELLOW}Save the credentials above — they are not shown again.${NC}"
echo ""
echo -e "  Default admin login:  ${ADMIN_USERNAME} / ${ADMIN_PASSWORD}"
echo ""
echo -e "  Architecture:  Standalone (FFmpeg + Nginx, no XC-VM)"
echo -e "  HLS segments:  RAM-backed tmpfs at ${HLS_DIR}"
echo ""
echo -e "  Useful commands:"
echo -e "    Start all ingests:     php artisan ingest:ensure-all"
echo -e "    Check channel health:  php artisan channels:auto-check-health"
echo -e "    Watchdog:              php artisan channels:watchdog"
echo -e "    Scan multicast:        php artisan channels:scan-multicast udp://@239.0.0.1:1234"
echo ""
echo -e "  Logs:"
echo -e "    Middleware:  ${APP_DIR}/storage/logs/laravel.log"
echo -e "    Queue:       ${APP_DIR}/storage/logs/queue.log"
echo -e "    Nginx:       /var/log/nginx/error.log"
echo ""
