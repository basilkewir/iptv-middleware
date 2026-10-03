# IPTV Middleware

A comprehensive IPTV middleware platform (Streambox) built with Laravel 10.  
Channels, VOD, EPG, subscriptions, payments, multicast/UDP ingest, and a
self-contained FFmpeg playout engine — no external streaming engine.

## Architecture

```
Internet / IPTV Players
        │
        ▼
┌───────────────────────┐   port 25460 (public)
│  IPTV Middleware      │  ← only public-facing panel
│  (Laravel / Streambox)│
│  • Admin panel        │
│  • Xtream Codes API   │
│  • HLS ingest/proxy   │
│  • VOD upload/serve   │
│  • EPG / subscriptions│
└──────────┬────────────┘
           │ spawns ffmpeg (local processes only)
           ▼
┌───────────────────────┐   filesystem / tmpfs
│  FFmpeg playout       │
│  • Stage 1: -c copy   │  ← zero-freeze loop
│  • Stage 2: encode +  │  ← overlays, logo, clock
│    overlays           │
│  → HLS segments       │
└──────────┬────────────┘
           │ X-Accel-Redirect
           ▼
        Nginx (serves segments)
```

- The middleware is the **single source of truth**: all channels, users, VOD
  and bouquets live here. Nothing else is a dependency.
- Player requests (`/live/`, `/movie/`, `/series/`, `/player_api.php`) are
  authenticated by the middleware; FFmpeg writes HLS segments to disk (RAM-backed
  tmpfs) and Nginx serves them via `X-Accel-Redirect`.
- UDP/multicast reading, scanning, and VOD file upload all live in the
  middleware (Streambox) layer.
- There is no second engine to install, patch, sync to, or keep on a private
  loopback port.

## Requirements

- Ubuntu 22.04 or 24.04 (bare metal or VPS — no Docker)
- Root access for the installer
- PHP 8.2, MySQL 8, Redis, Nginx, FFmpeg (all installed automatically)

## One-Command Install

```bash
sudo bash install.sh
```

Optional flags:

```bash
sudo bash install.sh --domain iptv.example.com --port 25460 --app-dir /opt/iptv-middleware
```

The installer:
1. Installs all system packages (PHP 8.2, MySQL, Redis, Nginx, FFmpeg, Node 20)
2. Creates the middleware MySQL database and user
3. Configures Redis
4. Installs PHP dependencies and builds the frontend assets
5. Writes `.env` with auto-generated secrets
6. Runs database migrations and seeds
7. Configures the public Nginx vhost, kernel tuning (BBR) and PHP-FPM
8. Sets up Supervisor (queue worker + scheduler)
9. Installs systemd services (watchdog, ingest, playout, FFmpeg purge) and file permissions
10. Mounts a RAM-backed tmpfs over the HLS segment directory
11. Opens UFW for the middleware port only
12. Prepares the offline "channel is down" video and reloads services

## Manual Setup (development)

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
# Edit .env — set DB, Redis, LICENSE_JWT_SECRET values
php artisan migrate
php artisan db:seed
npm run dev
php artisan serve --port=25460
```

## API

Versioned under `/api/v1`:

| Endpoint | Description |
|---|---|
| `POST /api/v1/auth/register` | Register |
| `POST /api/v1/auth/login` | Login |
| `GET /api/v1/channels` | Channel list |
| `GET /api/v1/vod` | VOD list |
| `GET /api/v1/epg` | EPG |
| `POST /api/v1/subscription/subscribe` | Subscribe |
| `POST /api/v1/payment/invoice` | Create invoice |

Xtream Codes API (for IPTV players):

| Endpoint | Description |
|---|---|
| `GET /player_api.php` | Auth + stream lists |
| `GET /live/{u}/{p}/{id}.m3u8` | Live stream |
| `GET /movie/{u}/{p}/{id}.mp4` | VOD |
| `GET /series/{u}/{p}/{id}.mp4` | Series episode |
| `GET /get.php` | M3U playlist |

## Project Structure

```
app/
├── Console/Commands/       # Artisan commands (streams:*, ingest:*, epg:*, channels:*)
├── Http/Controllers/
│   ├── Admin/              # Streambox admin panel controllers
│   ├── Api/                # REST API controllers
│   └── XtreamController.php # Xtream Codes protocol + HLS ingest
├── Models/                 # Eloquent models
├── Services/
│   ├── AdminChannel/       # Two-stage playout engine (MyChannelHlsService)
│   ├── StreamingService/   # Multicast/UDP ingest, HLS
│   └── VOD/                # VOD upload, transcoding
config/
├── playout.php             # Playout/encoder config (threads, FIFO, overlays)
├── streaming.php           # HLS/ingest config
├── epg.php                 # EPG config
deploy/
├── nginx-middleware.conf   # Production Nginx vhost
├── iptv-playout@.service   # Systemd per-channel playout unit
├── iptv-playout-ctl        # Privileged start/stop helper (sudoers)
├── iptv-watchdog.*         # Systemd watchdog
├── iptv-ingest.service     # Systemd ingest service
└── iptv-purge-ffmpeg.*     # Systemd FFmpeg cleanup
install.sh                  # Bare-metal auto-installer
```

## Playout

Every "My Channel" runs as two supervised FFmpeg processes connected by a FIFO:

- **Stage 1** — stream-copy loop (`-c copy -stream_loop -1` over a concat list)
  reading the pre-normalised intermediates and feeding Stage 2 through a FIFO.
  No re-encode, so the loop point never freezes.
- **Stage 2** — one encode pass applying fps/timebase normalisation plus the
  logo, watermark, clock, ticker and rotating-canvas overlays, writing HLS
  segments to disk.

Because Stage 2 reads from a pipe, ticker text is re-read from disk every
frame. Logo and watermark canvas changes restart Stage 2 so FFmpeg decodes the
new image. Graph or canvas changes coordinate a fresh Stage 1 NUT header with
Stage 2, keeping the channel supervisor alive and appending HLS segments to
the existing manifest. The current playlist item is placed first for the
reload.

Playlist items can be categorized as **Program** or **Jingle**. Saving category
changes updates the live overlay gate; all channel overlays are hidden while a
jingle is on air. Applying the category uses the coordinated reload above; it
does not stop the broadcast service or reset the HLS manifest.

```bash
# Start / stop / restart a channel's playout (privileged helper, no shell)
sudo iptv-playout-ctl {start|stop|restart|status|show} <channel-slug>

# Run the Linux end-to-end playout test: five playlist clips (one is 1 second), HLS output,
# live playlist switching, overlays, encoder recovery and segment timeline
./deploy/playout-smoke-test.sh
```

The smoke test requires Linux, FFmpeg, FFprobe, PHP and Python 3. It renders
five visually distinct clips, including a one-second item, and verifies each
reaches HLS output while the clock and ticker overlays are enabled. It also
checks coordinated producer/encoder reloads and respects HLS timestamp
discontinuities during segment validation.

### Manual server update

To update an existing Ubuntu installation directly from GitHub, download and
run the updater on that server as root (replace the app path if it differs):

```bash
curl -fsSL https://raw.githubusercontent.com/basilkewir/iptv-middleware/main/update.sh \
  -o /tmp/iptv-update.sh
sudo bash /tmp/iptv-update.sh --app-dir /opt/middleware
```

The updater stages the latest `main` checkout, builds production Composer and
frontend dependencies, then applies the release to the installed app. If Git, Composer, or rsync are missing, it installs them through APT. It
installs Node.js 20 from NodeSource when no supported Node.js is present, and
requires PHP 8.1+. It preserves `.env` and `storage/`,
runs pending migrations, refreshes Laravel caches, restarts the queue worker,
and reloads PHP-FPM. It does not restart any channel playout process. It will
stop before deploying if the app path or running PHP-FPM/queue services do not
match an existing installation.

### Automatic deployment to installed servers

Pushing to `main` runs the playout regression tests and builds a production
release, including Composer dependencies and frontend assets. It deploys only
to explicitly registered Linux self-hosted runners whose configured app
directory contains an installed Laravel stack, `.env`, prepared HLS storage, an active
PHP-FPM service, and an active queue worker. Servers that do not meet these
conditions are reported as skipped. Live FFmpeg playout processes are not
restarted.

For each installed server:

1. Install a dedicated GitHub Actions self-hosted runner for this repository.
   Give it the unique label used for that server below. Run the runner as the
   app deployment user (not root), and restrict repository Actions permissions
   and runner access to trusted maintainers.
2. Ensure that runner user can write to the app directory, `app/`,
   `bootstrap/cache/`, and `storage/`, and can run the following service
   commands without a password: reload the active `php*-fpm` unit, and restart
   either `middleware-queue.service` or Supervisor's `iptv-queue`. For example,
   a systemd-queue server can grant only the required actions in a validated
   sudoers file (replace `iptv-deploy` and the PHP version to match that host):

   ```sudoers
   iptv-deploy ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.3-fpm.service
   iptv-deploy ALL=(root) NOPASSWD: /usr/bin/systemctl restart middleware-queue.service
   ```

   A Supervisor-managed queue instead needs narrowly scoped permissions for
   `supervisorctl status iptv-queue` and `supervisorctl restart iptv-queue`.
   Validate the sudoers file with `visudo -cf` before installing it.
3. In repository **Settings → Secrets and variables → Actions → Variables**,
   add `DEPLOY_TARGETS` as a JSON array. Each entry maps a unique runner label
   to its existing app directory:

   ```json
   [
     {"runner": "iptv-guestvue", "app_dir": "/opt/middleware"},
     {"runner": "iptv-server-2", "app_dir": "/opt/iptv-middleware"}
   ]
   ```

The runner label must be configured on that server's self-hosted runner. The
deployment preserves `.env`, user uploads, HLS files, and other `storage/`
contents; it applies pending migrations, clears Laravel's optimized caches,
restarts the queue worker, and reloads PHP-FPM. The package already contains
production PHP and frontend dependencies, so Composer and Node.js are not
required on the installed server. If `DEPLOY_TARGETS` is empty, CI and release
builds still run but no server deployment is attempted.

## UDP / Multicast

Channels with `udp://` or `rtp://` sources are automatically routed through
the shared multicast group reader — one FFmpeg process reads the entire mux
and fans out per-program HLS, preventing socket buffer overflow.

Scan for multicast channels:

```bash
php artisan channels:scan-multicast
```

## VOD Upload

Upload via the admin panel (`/admin/vod`) or the API:

```bash
POST /admin/vod/upload          # file upload
POST /admin/vod/import/url      # import from URL
POST /admin/vod/import/xtream   # import from Xtream source
```

Uploaded files are stored in `storage/app/public/vod/` and served via the
`/storage/` Nginx alias.
