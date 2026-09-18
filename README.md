# IPTV Middleware

A comprehensive IPTV middleware platform (Streambox) built with Laravel 10.  
Channels, VOD, EPG, subscriptions, payments, multicast/UDP ingest, and a
transparent XC-VM streaming engine under the hood.

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
           │ loopback only (127.0.0.1:25462)
           ▼
┌───────────────────────┐   hidden from internet
│  XC-VM Engine         │  ← streaming engine
│  (Xtream Codes OSS)   │
│  • Live stream mgmt   │
│  • VOD/series engine  │
│  • Line management    │
└───────────────────────┘
```

- XC-VM is **never reachable from the internet** — bound to `127.0.0.1` only.
- The middleware is the **single source of truth**: all channels, users, VOD,
  and bouquets are managed here and synced to XC-VM automatically.
- Player requests (`/live/`, `/movie/`, `/series/`, `/player_api.php`) are
  authenticated by the middleware then proxied to XC-VM over loopback.
- UDP/multicast reading, scanning, and VOD file upload all remain in the
  middleware (Streambox) layer.

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
2. Creates isolated MySQL databases for the middleware and XC-VM
3. Clones and configures XC-VM bound to `127.0.0.1:25462`
4. Writes `.env` with auto-generated secrets
5. Runs migrations, seeds, and builds frontend assets
6. Configures Nginx (public middleware vhost + loopback XC-VM vhost)
7. Sets up Supervisor (queue worker + scheduler)
8. Installs systemd services (watchdog, ingest, FFmpeg purge)
9. Configures UFW to block the XC-VM port externally
10. Runs the initial XC-VM sync

## Manual Setup (development)

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
# Edit .env — set DB, Redis, XC_VM_* values
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
├── Console/Commands/       # Artisan commands (xcvm:sync, streams:*, epg:*)
├── Http/Controllers/
│   ├── Admin/              # Streambox admin panel controllers
│   ├── Api/                # REST API controllers
│   └── XtreamController.php # Xtream Codes protocol + HLS ingest
├── Models/                 # Eloquent models
├── Services/
│   ├── XcVm/               # XC-VM client, sync, player proxy
│   ├── StreamingService/   # Multicast/UDP ingest, HLS
│   └── VOD/                # VOD upload, transcoding
config/
├── xcvm.php                # XC-VM engine config
├── streaming.php           # HLS/ingest config
├── epg.php                 # EPG config
deploy/
├── nginx-middleware.conf   # Production Nginx vhost
├── iptv-watchdog.*         # Systemd watchdog
├── iptv-ingest.service     # Systemd ingest service
└── iptv-purge-ffmpeg.*     # Systemd FFmpeg cleanup
install.sh                  # Bare-metal auto-installer
```

## XC-VM Sync

The middleware syncs to XC-VM automatically every 5 minutes and on every
channel/user/VOD change (live sync via model observers).

Manual sync:

```bash
# Full sync
php artisan xcvm:sync

# Sync only channels
php artisan xcvm:sync --type=channel

# Sync one channel
php artisan xcvm:sync --type=channel --id=5

# Test connection
php artisan xcvm:test
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
`/storage/` Nginx alias. XC-VM fetches them over the loopback VOD bridge
(`http://127.0.0.1:25462/vod_bridge/`).
