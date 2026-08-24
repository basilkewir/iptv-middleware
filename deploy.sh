#!/usr/bin/env bash
set -euo pipefail

# Deploy helper for the middleware app.
# Run this on the Ubuntu server as the kotelhms user.

PROJECT_NAME=middleware_app
APP_DIR="$HOME/middleware"

if [ "$(id -un)" != "kotelhms" ]; then
  echo "Warning: it's recommended to run this script as user 'kotelhms'. Continuing..."
fi

command -v docker >/dev/null 2>&1 || { echo "docker is not installed. Install Docker first."; exit 1; }
command -v docker-compose >/dev/null 2>&1 || true

mkdir -p "$APP_DIR"
cd "$APP_DIR"

# Ensure .env exists
if [ ! -f .env ]; then
  if [ -f .env.example ]; then
    cp .env.example .env
    echo "Created .env from .env.example — edit .env before continuing if needed."
  else
    echo "No .env or .env.example found — create a .env with required settings and re-run."
    exit 1
  fi
fi

echo "Building and starting containers with Docker Compose (project: $PROJECT_NAME)..."

# Use Docker Compose plugin if available
if docker compose version >/dev/null 2>&1; then
  docker compose -p "$PROJECT_NAME" up -d --build
else
  docker-compose -p "$PROJECT_NAME" up -d --build
fi

echo "Containers started — listing running containers for project $PROJECT_NAME:"
docker ps --filter "name=$PROJECT_NAME" --format "table {{.Names}}\t{{.Image}}\t{{.Status}}\t{{.Ports}}"

echo
echo "Run a quick health check (adjust URL/port in .env if needed):"
if grep -q APP_URL .env 2>/dev/null; then
  APP_URL=$(grep '^APP_URL=' .env | cut -d'=' -f2-)
  echo "curl -I $APP_URL"
  curl -I --max-time 5 "$APP_URL" || echo "Health check failed — check container logs with 'docker compose -p $PROJECT_NAME logs'"
else
  echo "No APP_URL set in .env — check service ports and test manually."
fi

echo
echo "To rollback this deployment without impacting other projects, run:" 
echo "  docker compose -p $PROJECT_NAME down"

exit 0
