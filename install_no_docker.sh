#!/usr/bin/env bash
set -euo pipefail

# Non-docker install helper for the middleware app.
# Intended to run on the Ubuntu server as user kotelhms.

if [ "$(id -un)" != "kotelhms" ]; then
  echo "Warning: recommended to run as user 'kotelhms'. Continuing..."
fi

APP_DIR="$HOME/middleware"

if [ ! -d "$APP_DIR" ]; then
  echo "Directory $APP_DIR does not exist. Extract the application into $APP_DIR and re-run this script." >&2
  exit 1
fi

cd "$APP_DIR"

echo "Checking required commands..."

if ! command -v php >/dev/null 2>&1; then
  echo "PHP not found — installing PHP and required extensions (will prompt for sudo)."
  sudo apt update
  sudo apt install -y php php-cli php-mbstring php-xml php-curl php-zip php-mysql unzip git curl build-essential
fi

if ! command -v composer >/dev/null 2>&1; then
  echo "Installing Composer..."
  sudo apt install -y curl php-cli unzip
  curl -sS https://getcomposer.org/installer | php
  sudo mv composer.phar /usr/local/bin/composer
fi

if ! command -v node >/dev/null 2>&1 || ! command -v npm >/dev/null 2>&1; then
  echo "Node.js or npm missing — installing Node.js (will prompt for sudo)."
  sudo apt install -y nodejs npm
fi

echo "Installing PHP dependencies (composer)."
composer install --no-dev --optimize-autoloader -n

if [ ! -f .env ]; then
  if [ -f .env.example ]; then
    cp .env.example .env
    echo "Created .env from .env.example — edit .env before starting the app."
  else
    echo "No .env or .env.example found — create .env with required settings before starting." >&2
  fi
fi

echo "Generating application key (if Laravel)."
if php artisan --version >/dev/null 2>&1; then
  php artisan key:generate --force || true
fi

if [ -f package.json ]; then
  echo "Installing JS dependencies and building assets."
  npm ci --silent || npm install --silent
  if grep -q "build" package.json; then
    npm run build --silent || true
  fi
fi

# Create simple start/stop scripts that run PHP built-in server on 127.0.0.1:8081
cat > start_server.sh <<'EOF'
#!/usr/bin/env bash
cd "$HOME/middleware"
nohup php artisan serve --host=127.0.0.1 --port=8081 >> storage/logs/serve.log 2>&1 &
echo $! > /tmp/middleware_app.pid
echo "Started middleware (PID $(cat /tmp/middleware_app.pid)). Logs: storage/logs/serve.log"
EOF
chmod +x start_server.sh

cat > stop_server.sh <<'EOF'
#!/usr/bin/env bash
if [ -f /tmp/middleware_app.pid ]; then
  kill "$(cat /tmp/middleware_app.pid)" || true
  rm -f /tmp/middleware_app.pid
  echo "Stopped middleware using PID file."
else
  pkill -f "php artisan serve --host=127.0.0.1 --port=8081" || true
  echo "Stopped middleware by process name."
fi
EOF
chmod +x stop_server.sh

echo
echo "Install complete. To start the app (non-production, avoids changing system services):"
echo "  cd $APP_DIR && ./start_server.sh"
echo "To stop:"
echo "  cd $APP_DIR && ./stop_server.sh"
echo
echo "Notes:"
echo "- This uses the PHP built-in server on 127.0.0.1:8081 to avoid modifying system nginx configuration."
echo "- For production-grade deployment you may prefer Docker or a dedicated webserver configuration; let me know if you want that."

exit 0
