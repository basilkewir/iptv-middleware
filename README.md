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

Because Stage 2 reads from a pipe, editing an overlay (logo, clock, ticker
text) is re-read from disk every frame — those changes need **no restart**.
Only a change to the encoder graph itself (resolution, fps, filter chain)
bumps the systemd unit.

```bash
# Start / stop / restart a channel's playout (privileged helper, no shell)
sudo iptv-playout-ctl {start|stop|restart|status|show} <channel-slug>

# Verify the generated playout/stage-2 scripts are valid bash
./deploy/playout-smoke-test.sh
```

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
