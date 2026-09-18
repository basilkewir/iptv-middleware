# Continuation Note — Dashboard "Restart Ingest" Fix & Follow-ups

> **Generated:** Session continuation after reviewing `FIX_SUMMARY.md` and the full codebase.
> **Last commit:** `40d4a0f` — `install.sh: fix masked MySQL, real XC-VM installer, auto-start all services`
> **Status:** Working tree was clean at start of session. Changes below applied during this session.

---

## 1. Background — The Original Problem

Clicking the per-channel **restart (↻)** button in the Admin Dashboard's *HLS Ingests* panel produced an
**HTTP 405 – Method Not Allowed**. The ingest was never restarted.

**Root cause:** The dashboard submits a `POST` to a URL that was not registered as a `POST` route. Route-name
ambiguity among the many `channels.*` routes (across the admin-group and client-group namespaces) caused the
POST to land on a verb-mismatched endpoint.

**Fix applied (per `FIX_SUMMARY.md`):** A dedicated, uniquely-named `POST /admin/channels/{channel}/refresh-ingest`
route was added, wired to `DashboardController@refreshIngest`, which delegates to
`XtreamController::restartHlsStream()`. The Vue frontend uses `router.post(route('admin.channels.refresh-ingest', ...))`.

---

## 2. Verified Current State (Already Committed)

| Component | File | Status |
|---|---|---|
| POST route registration | `routes/web.php:351` | Inside `admin.`-prefixed group → resolves to `admin.channels.refresh-ingest`, URI `admin/channels/{channel}/refresh-ingest`, method POST |
| Vue frontend action | `resources/js/Pages/Admin/Dashboard/Index.vue:374` | Uses `router.post(route('admin.channels.refresh-ingest', { channel: id }), ...)` with spinner, `preserveScroll`, `preserveState`, and `router.reload({ only: ['stats'] })` on success |
| Controller handler | `app/Http/Controllers/Admin/DashboardController.php:66` | `refreshIngest(Request, Channel, XtreamController)` — 404s if channel inactive, calls `$xtream->restartHlsStream($channel)`, returns JSON or redirect |
| Restart logic | `app/Http/Controllers/XtreamController.php:660` | `restartHlsStream(Channel $channel)` handles both UDP/multicast (group reader stop+ensure) and single-channel ingest (PID kill, clean, respawn) |

**Route inspection confirmed:**
```
POST   admin/channels/{channel}/refresh-ingest  admin.channels.refresh-ingest  DashboardController@refreshIngest
```

---

## 3. Changes Applied in This Session

### 3a. Fixed: Unreachable multicast sweep route (FIX_SUMMARY recommendation #1)

**Before:** `GET /channels/admin/{channel}/sweep` (name `admin.channels.scan-multicast`) was registered at line 722 —
**after** the `/{any}` catch-all at line 720. The catch-all shadowed it, making it unreachable.

**After:**
- Moved the route **above** the catch-all so it can be matched.
- Added `auth:web` + `AdminMiddleware` middleware for access control (it previously had no middleware at all — a security gap on a route that triggers a multicast scan).
-  Kept the route name `admin.channels.scan-multicast` unchanged so existing frontend references in `Create.vue:655` and `Edit.vue:774` continue to work.

**Files changed:**
- `routes/web.php` — reordered the sweep route above the catch-all, wrapped in auth middleware.

**Before:**
```php
// catch-all (line 720)
Route::get('/{any}', fn () => redirect()->route('login'))->where('any', '.*');
// unreachable (line 722)
Route::get('/channels/admin/{channel}/sweep', [AdminChannelController::class, 'scanMulticast'])
    ->name('admin.channels.scan-multicast');
```

**After:**
```php
// Multicast sweep (above catch-all so it is reachable)
Route::middleware(['auth:web', \App\Http\Middleware\AdminMiddleware::class])
    ->get('/channels/admin/{channel}/sweep', [AdminChannelController::class, 'scanMulticast'])
    ->name('admin.channels.scan-multicast');

// Catch-all
Route::get('/{any}', fn () => redirect()->route('login'))->where('any', '.*');
```

**Verified:**
```
GET|HEAD  channels/admin/{channel}/sweep  admin.channels.scan-multicast  AdminChannelController@scanMulticast
```

> **Note:** The frontend `Create.vue:655` and `Edit.vue:774` call this via
> `router.post(route('admin.channels.scan-multicast', { url: form.stream_url }))`. This passes a `url`
> query-param-style object to a GET route whose parameter is `{channel}`, not `{url}`. The route will receive
> an empty `$channel` binding. This is a **pre-existing bug** not introduced by this session — flagged here
> for a future session but left untouched.

### 3b. Fixed: Missing `.env.example` vars (new finding)

`install.sh` writes `ADMIN_USERNAME`, `ADMIN_PASSWORD`, and `ADMIN_EMAIL` to `.env`, but these were absent from
`.env.example`. The `UserSeeder` (`database/seeders/UserSeeder.php:14-16`) reads them via `env('ADMIN_USERNAME', ...)`,
so without the example entries new developers have no guidance.

**Added to `.env.example`** (between `JWT_TTL` and `STREAM_SERVER_IP`):
```
ADMIN_USERNAME=admin
ADMIN_PASSWORD=admin123
ADMIN_EMAIL=admin@iptv-middleware.com
```

---

## 4. Remaining Follow-up (Not Yet Addressed)

### 4a. Double-prefixed route names (FIX_SUMMARY recommendation #2) — PENDING

~30 admin-channel routes at `routes/web.php:232-271` use explicit names like `admin.channels.index` while
already sitting inside the `admin.` name-prefixed group. Laravel concatenates the group prefix, producing
**`admin.admin.channels.*`** for each. Confirmed via route inspection:

```
admin.admin.channels.index        →  admin/channels/admin
admin.admin.channels.create       →  admin/channels/admin/create
admin.admin.channels.store        →  admin/channels/admin
admin.admin.channels.toggle-status → admin/channels/admin/{channel}/toggle-status
... (30 total)
```

**FIX_SUMMARY assessment:** "Harmless today since the frontend uses the same strings, but worth normalizing."

**Recommended fix:** Remove the redundant `admin.` prefix from each explicit route name in that block
(e.g., `admin.channels.index` → `channels.index`), so they resolve to `admin.channels.index` via the group
prefix. Then update all frontend references from `admin.admin.channels.*` to `admin.channels.*`.

**Risk:** The frontend references were generated from the current (double-prefixed) route names, so both
sides must be changed together. Use `grep -rn "admin\.admin\."` across `resources/js/` to find all references.

### 4b. Deploy cache clearing (FIX_SUMMARY recommendation #3) — DOCUMENTED, MANUAL

On deploy, run:
```bash
php artisan route:clear && php artisan config:clear && php artisan view:clear
php artisan route:cache && php artisan config:cache
```
This is especially important after adding/modifying routes (the `ziggy.js` asset embeds the route list and
must be regenerated: `php artisan ziggy:generate`).

---

## 5. Architecture Context for Next Session

### Key files for the streaming/XcVm subsystem
| File | Role |
|---|---|
| `app/Services/XcVm/XcVmClient.php` | Low-level HTTP client wrapping the XC-VM REST API (streams, movies, series, episodes, users, bouquets, categories, settings) |
| `app/Services/XcVm/XcVmSyncService.php` | Orchestrates full sync, incremental sync, prune |
| `app/Services/XcVm/UdpXcVmBridge.php` | Pushes local UDP HLS URLs into XC-VM for proxy streaming |
| `app/Services/XcVm/XcVmPlayerProxy.php` | Proxies authenticated player requests to XC-VM |
| `app/Services/XcVm/XcVmUrl.php` | Builds XC-VM stream/playlist URLs |
| `app/Services/XcVm/Syncers/ChannelSyncer.php` | Per-entity sync logic for channels |
| `app/Services/StreamingService/MulticastIngestService.php` | Manages the shared UDP multicast group reader (one ffmpeg per mux, multiple HLS outputs) |
| `app/Services/StreamingService/FlussonicService.php` | Flussonic playout API wrapper |
| `app/Http/Controllers/XtreamController.php` | Xtream Codes-compatible API + HLS ingest management |
| `app/Console/Commands/AutoCheckSourceHealth.php` | Probes all active sources every 60s, auto-failovers to backups |

### Scheduled commands (`app/Console/Kernel.php`)
| Command | Schedule | Purpose |
|---|---|---|
| `channels:auto-check-health` | Every 60s | Probe all active sources, auto-failover to backups |
| `channels:probe-sources` | Every 3min | Per-source health status for admin UI |
| `channels:watchdog` | Every 30s | Restart stale UDP ingests, cleanup dead processes |
| `xcvm:sync` | Every 5min (configurable) | Full XC-VM data sync |
| `xcvm:sync-udp` | — | Bridge UDP→XC-VM for proxied playback |
| `streams:prepare-offline` | — | Prepare offline "channel is down" HLS fallback |

### Deployment
- `install.sh` — bare-metal installer (Ubuntu 22.04/24.04, no Docker). Installs MariaDB, Redis, Nginx, PHP deps, XC-VM, supervisor, systemd units.
- XC-VM is bound to `127.0.0.1:25462` only (never exposed). Middleware proxies player requests to it.
- Systemd units: `xcvm.service`, `iptv-watchdog.{service,timer}`, `iptv-ingest.service`, `iptv-purge-ffmpeg.{service,timer}`.
- Supervisor manages the scheduler and queue workers.

---

## 6. Next Steps / Action Items

1. **PENDING — Normalize double-prefixed route names** (Section 4a): Remove redundant `admin.` from ~30 route names at `routes/web.php:232-271`, then update frontend references in `resources/js/` from `admin.admin.channels.*` → `admin.channels.*`. Re-run `php artisan ziggy:generate`.

2. **PENDING — Review sweep route controller param mismatch** (Section 3a note): `Create.vue`/`Edit.vue` pass `url` as the route parameter but the route expects `channel`. Either fix the route/parameter handling or confirm this feature is unused.

3. **PENDING — Regenerate `ziggy.js`**: After any route changes, run `php artisan ziggy:generate` so the frontend route helper stays in sync with the backend.

4. **PENDING — Verify the fix in a real environment**: After deploying, click the ↻ button on the dashboard HLS Ingests panel and confirm it restarts without 405. The `restartHlsStream` method handles both UDP (group reader restart) and single-channel ingest restart paths.

5. **ONGOING — Clear caches on deploy**: `php artisan route:clear && php artisan config:clear && php artisan ziggy:generate`

---

## 7. Git Diff Summary (this session)

```
routes/web.php                          | 14 +++++++-------
.env.example                            |  3 +++

2 files changed, sweep route moved above catch-all + auth middleware added; 3 missing env vars documented.
```