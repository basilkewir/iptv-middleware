# IPTV Middleware & HMS System — API Documentation

> Comprehensive API reference for mobile application developers integrating with the
> **IPTV Middleware** (Streambox) platform and the **HMS — Hotel / License Management System**.
>
> - **Middleware version:** Laravel 10 / Streambox (standalone FFmpeg + Nginx)
> - **Default HTTP port:** `25460` (configurable via `STREAM_SERVER_PORT`)
> - **API version prefix:** `/api/v1`
> - **Source commit:** `00ec8e7` on `main`

---

## Table of Contents

1. [Architecture Overview](#1-architecture-overview)
2. [Base URLs & Versioning](#2-base-urls--versioning)
3. [Authentication](#3-authentication)
4. [HMS License System API](#4-hms-license-system-api)
5. [IPTV Middleware REST API (v1)](#5-iptv-middleware-rest-api-v1)
6. [Xtream Codes API](#6-xtream-codes-api)
7. [Streaming Playback URLs](#7-streaming-playback-urls)
8. [Data Models (Response Schemas)](#8-data-models-response-schemas)
9. [Standard Response Envelope](#9-standard-response-envelope)
10. [Error Responses](#10-error-responses)
11. [Pagination](#11-pagination)
12. [Rate Limiting](#12-rate-limiting)
13. [Full Route Summary](#appendix-a-full-route-summary)
14. [Mobile App Integration Guide](#appendix-b-mobile-app-integration-guide)

---

## 1. Architecture Overview

The platform consists of two layers. The **HMS (Hotel Management System)** governs
license validation for hotel deployments. The **IPTV Middleware** serves content
catalogs, user data, and live/VOD streaming to clients. Its **FFmpeg + Nginx
streaming engine** delivers HLS segments with one-to-many efficiency.

```
┌─────────────────────────────────────────────────────────────┐
│                      Mobile Application                     │
├─────────────────────────────────────────────────────────────┤
│  HMS License API (JWT)   │  Middleware REST API (Sanctum)  │
│  /api/v1/license/*      │  /api/v1/auth/, /channels, etc   │
├─────────────────────────────────────────────────────────────┤
│                    IPTV Middleware (Laravel)               │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────┐ │
│  │  License     │  │  Content     │  │  Playout Engine  │ │
│  │  System      │  │  Catalog     │  │  (FFmpeg)        │ │
│  └──────┬───────┘  └──────┬───────┘  └────────┬─────────┘ │
│  kewirdev.com←remote→ MySQL DB ←←→ FFmpeg/HLS + Nginx       │
└─────────────────────────────────────────────────────────────┘
```

### HMS (Hotel / License Management System)

- Validates license keys against **kewirdev.com** (remote authority), with
  **local database fallback** when the remote server is unreachable.
- Issues **JWT tokens** (HS256) encoding the license, device, hotel, and features.
- Enforces **device binding** (fingerprint-based) and per-license device limits.
- Supports **room-count synchronization** — HMS reports managed rooms, validated
  against the license's `max_users` feature flag.

### IPTV Middleware REST API

- **Sanctum API tokens** for subscriber/user authentication.
- **License JWT** (Bearer token) for license-protected endpoints via the `license`
  middleware (profile, favorites, watch history, reviews, subscriptions, payments).

### Streaming Engine

Each channel runs as two supervised FFmpeg processes: Stage 1 stream-copies a
looping playlist into a FIFO (no re-encode, so the loop point never freezes),
Stage 2 encodes once and burns in the overlays, writing HLS segments to disk.
Nginx serves segments to all viewers via `X-Accel-Redirect` (near-zero PHP
overhead per segment). The Xtream Codes API (`/player_api.php`) maintains player
compatibility (TiviMate, Smarters, Formuler, GSE, IPTV Smarters).

---

## 2. Base URLs & Versioning

### Middleware REST API (HMS + Subscriber)

```
https://<server-ip>:25460/api/v1/
```

All REST routes are versioned under `/api/v1/` (see `RouteServiceProvider`).
The `api` middleware group applies: Sanctum stateful-check, API rate throttling
(`60 req/min`), and route-model binding.

### Xtream Codes API & Streaming

```
https://<server-ip>:25460/
```

| Layer        | Path pattern                                          |
|--------------|-------------------------------------------------------|
| Control plane| `/player_api.php?action=...`                         |
| Playlist     | `/get.php?username=...`                               |
| EPG (XMLTV)  | `/xmltv.php?username=...`                             |
| HLS live     | `/{username}/{password}/live/{stream_id}.m3u8`        |
| HTTP-TS      | `/ts/{username}/{password}/{stream_id}`               |
| VOD          | `/{username}/{password}/vod/{stream_id}.{ext}`        |
| HLS segments | `/hls/{key}/{file}`                                   |

### Config Keys

| Variable               | Default           | Env key                  |
|------------------------|-------------------|--------------------------|
| `APP_URL`              | `http://<ip>:25460` | `APP_URL`              |
| Stream server port     | `25460`           | `STREAM_SERVER_PORT`     |
| JWT secret (license)   | —                 | `LICENSE_JWT_SECRET` |

> **Important:** The JWT secret is read from `config('license.jwt_secret')` which
> maps to the `LICENSE_JWT_SECRET` environment variable (NOT `JWT_SECRET` in `.env.example`,
> which is a legacy/leftover key). Ensure `LICENSE_JWT_SECRET` is set to a
> `base64_encode(random_bytes(32))` value before using license endpoints.

---

## 3. Authentication

This platform supports **two** authentication systems. The correct one depends on
the client type:

| Client type              | System   | Header                          | Middleware     |
|--------------------------|----------|---------------------------------|----------------|
| Hotel / HMS device app   | JWT      | `Authorization: Bearer <jwt>`   | `license`      |
| IPTV subscriber / OTT    | Sanctum  | `Authorization: Bearer <token>` | `auth:sanctum` |

### 3.1 HMS License System (JWT)

**Flow:**

1. The hotel deploys the middleware with a purchased license key.
2. On first launch, the mobile app validates the license:
   `POST /api/v1/license/validate` with the license key + device info.
3. The server validates against kewirdev.com (with local DB fallback), binds the
   device, and returns a **JWT token**.
4. Subsequent requests to **license-protected** endpoints include the JWT as a
   Bearer token.

The JWT is signed with HS256 using `LICENSE_JWT_SECRET` and contains these claims:

| Claim                | Type          | Description                                    |
|----------------------|---------------|------------------------------------------------|
| `iss`                | string        | Issuing server URL                             |
| `aud`                | string        | Audience: `"hotel-iptv-app"`                   |
| `iat`                | int           | Issued-at timestamp (Unix)                     |
| `exp`                | int           | Expiration timestamp (Unix, `iat + TTL`)       |
| `license_id`         | int           | Database ID of the `licenses` record           |
| `license_key`        | string        | The raw license key string                     |
| `device_id`          | int           | Database ID of the `license_devices` record    |
| `device_fingerprint` | string        | MD5 fingerprint of device attributes           |
| `hotel_id`           | string/null   | Hotel identifier from the license              |
| `license_type`       | string        | trial, basic, premium, enterprise, perpetual   |
| `features`           | array         | Feature flags                                  |

**Token TTL:** 3600 seconds (1 hour) by default — configurable via
`LICENSE_TOKEN_EXPIRATION` env var.

### 3.2 IPTV Subscriber Auth (Sanctum)

**Flow:**

1. `POST /api/v1/auth/register` or `POST /api/v1/auth/login`.
2. The server returns a **Sanctum personal-access token** (`plainTextToken`).
3. All subsequent subscriber-authenticated requests include:
   `Authorization: Bearer <plainTextToken>`
4. `POST /api/v1/auth/logout` revokes the current token.

> **Note on license-protected endpoints:** The `license` middleware (JWT) guards
> the `/user/profile`, `/favorites`, `/watch-history`, `/reviews`, `/subscription`,
> and `/payment` routes. These endpoints **require a valid license JWT** as a Bearer
> token. A Sanctum subscriber token alone is **not** sufficient for these routes.
> The mobile app should validate its license first and use the JWT for
> license-protected operations.

---

## 4. HMS License System API

All endpoints are under `/api/v1/`.

### License Types & Features

| Type         | Duration  | Max Devices | Channels | Rooms | Analytics | Custom Branding | API Access | Support        |
|--------------|-----------|-------------|----------|-------|-----------|-----------------|------------|----------------|
| `trial`      | 30 days   | 1           | 10       | 5     | No        | No              | No         | basic          |
| `basic`      | 365 days  | 3           | 50       | 25    | Yes       | No              | No         | standard       |
| `premium`    | 365 days  | 10          | 200      | 100   | Yes       | Yes             | Yes        | premium        |
| `enterprise` | 365 days  | 50          | unlimited| unlimited | Yes     | Yes             | Yes        | enterprise     |
| `perpetual`  | never     | 1000        | unlimited| unlimited | Yes     | Yes             | Yes        | premium+       |

### POST `/api/v1/license/validate` — Validate License

Validates a license key against the remote kewirdev.com server (with local DB
fallback), binds the device, and returns a JWT token for subsequent API access.

**This endpoint is PUBLIC** — no prior authentication required.

#### Request

```
POST /api/v1/license/validate
Content-Type: application/json
```

| Parameter             | Type   | Required | Description                                           | Default      |
|-----------------------|--------|----------|-------------------------------------------------------|--------------|
| `license_key`         | string | Yes       | The hotel license key                                 | —            |
| `device_type`         | string | Yes       | `android_tv`, `smart_tv`, `management_backend`, `admin_panel` | — |
| `device_name`         | string | No        | Human-readable device name                            | `"Android TV"`|
| `device_model`        | string | No        | Device model string                                   | `""`         |
| `device_os`           | string | No        | Operating system                                      | `""`         |
| `device_os_version`   | string | No        | OS version                                            | `""`         |
| `app_version`         | string | No        | Application version                                   | `""`         |
| `device_id`           | string | No        | Unique device identifier (e.g., IMEI/Android ID)      | `""`         |

#### Example Request

```json
{
  "license_key": "HOTEL-ABC123-XYZ789",
  "device_type": "android_tv",
  "device_name": "Lobby TV",
  "device_model": "NVIDIA Shield TV Pro",
  "device_os": "Android TV",
  "device_os_version": "11",
  "app_version": "2.1.0",
  "device_id": "0123456789ABCDEF"
}
```

#### Success Response — `200 OK`

```json
{
  "success": true,
  "message": "License validated successfully (remote)",
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3Mi...",
    "expires_at": "2026-09-21T13:29:00.000000Z",
    "features": {
      "live_tv": true,
      "vod": true,
      "epg": true,
      "favorites": true,
      "watch_history": true,
      "max_users": 100,
      "max_channels": 200,
      "analytics": true,
      "custom_branding": true,
      "api_access": true
    },
    "device_id": 42
  },
  "license": {
    "id": 15,
    "license_key": "HOTEL-ABC123-XYZ789",
    "hotel_id": "hotel-001",
    "hotel_name": "Grand Plaza Hotel",
    "license_type": "premium",
    "status": "active",
    "max_devices": 5,
        "expires_at": "2027-01-15T00:00:00.000000Z"
  }
}
```

#### Error Responses

| Status | `success` | Message                                            | Meaning                                    |
|--------|-----------|----------------------------------------------------|--------------------------------------------|
| 422    | false     | `Validation failed.` (+ errors)                    | Missing or invalid parameters              |
| 403    | false     | Device limit reached or device blocked             | License has hit its device cap            |
| 401    | false     | Invalid license key                                | License rejected by remote & local        |
| 401    | false     | License is not valid or expired                   | License expired or suspended              |
| 500    | false     | An error occurred during license validation        | Server-side error                          |

#### Validation Response Shape

The raw service response (before HTTP wrapping) always includes:

| Field               | Type    | Description                          |
|---------------------|---------|--------------------------------------|
| `success`           | boolean | Whether validation succeeded         |
| `message`           | string  | Human-readable status                |
| `license`           | object/null | Serialized License model         |
| `license_id`        | int/null | License DB ID                       |
| `device_id`         | string/null | Client-supplied device_id          |
| `timestamp`         | string  | ISO-8601 validation time             |
| `processing_time`   | string  | e.g. `"15.23ms"`                    |
| `validation_method` | string  | `remote` or `local_fallback`         |
| `from_cache`        | boolean | Served from Redis cache              |
| `data` (nested)     | object  | `token`, `expires_at`, `features`, `device_id` (on success) |

### Other License Controller Methods (not yet routed)

The `LicenseController` exposes additional methods that exist in code but are
**NOT registered as API routes** in `routes/api.php`. To enable them, register
the routes inside the `license` middleware group:

| HTTP Method | Suggested Route                  | Controller Method          | Purpose                              |
|-------------|----------------------------------|----------------------------|--------------------------------------|
| POST        | `/api/v1/license/validate-token` | `LicenseController::validateToken` | Verify a JWT without re-binding |
| POST        | `/api/v1/license/refresh`        | `LicenseController::refreshToken`  | Issue a fresh JWT from an existing |
| GET         | `/api/v1/license/info`           | `LicenseController::info`          | Get license/device stats by key    |
| POST        | `/api/v1/license/sync-rooms`     | `LicenseController::syncRooms`     | Sync room count from HMS           |

#### POST (suggested) `/api/v1/license/validate-token`

Verify that a previously-issued JWT is still valid.

**Request:**

```json
{ "token": "eyJ0eXAi..." }
```

**Response — `200`:**

```json
{
  "success": true,
  "valid": true,
  "license": { /* License model */ },
  "device": { /* LicenseDevice model */ },
  "features": { /* feature array */ }
}
```

#### POST (suggested) `/api/v1/license/refresh`

Exchange an expiring JWT for a fresh one.

**Request:**

```json
{ "token": "eyJ0eXAi..." }
```

**Response — `200`:**

```json
{
  "success": true,
  "token": "eyJ0eXAi...new_token...",
  "expires_at": "2026-09-21T14:29:00.000000Z"
}
```

#### GET (suggested) `/api/v1/license/info`

Get statistics for a license key.

| Parameter     | Type   | Required | Description     |
|---------------|--------|----------|---------------------|
| `license_key` | string | Yes       | The license key     |

**Response — `200`:**

```json
{
  "license": { /* License with devices & validationLogs */ },
  "total_devices": 3,
  "active_devices": 2,
  "total_validations": 156,
  "recent_validations": 12,
  "last_validation": "2026-09-21T12:00:00.000000Z",
  "is_valid": true,
  "expires_at": "2027-01-15T00:00:00.000000Z",
  "features": { /* feature array */ }
}
```

#### POST (suggested) `/api/v1/license/sync-rooms`

Called by the HMS installation to report its current room count. The server
validates the count against `features.max_users` (`-1` = unlimited) and stores it
in `licenses.assigned_rooms`.

| Parameter       | Type | Required | Description                          |
|-----------------|------|----------|--------------------------------------|
| `license_key`   | string | Yes     | The license key                      |
| `device_id`     | string | Yes     | The device ID reporting              |
| `room_count`    | int  | Yes       | Number of rooms currently managed    |

**Success:**

```json
{ "success": true, "room_count": 42, "room_limit": 100, "allowed": true }
```

**Error — `400` (limit exceeded):**

```json
{ "success": false, "error": "Room limit exceeded: license allows 100 rooms, HMS reports 150.", "room_count": 150, "room_limit": 100, "allowed": false }
```

---

## 5. IPTV Middleware REST API (v1)

All endpoints are prefixed with `/api/v1/`.

### 5.1 Auth

#### POST `/api/v1/auth/register` — Register

Creates a new subscriber account and issues a Sanctum token.

**Request:**

| Parameter               | Type   | Required | Rule                                    |
|-------------------------|--------|----------|-----------------------------------------|
| `username`              | string | Yes       | Unique                                  |
| `email`                 | string | Yes       | Valid email, unique                     |
| `password`              | string | Yes       | Min 8 chars                             |
| `password_confirmation` | string | Yes       | Must match `password`                   |
| `first_name`            | string | Yes       | Max 255                                 |
| `last_name`             | string | Yes       | Max 255                                 |
| `phone`                 | string | No        | Max 50                                  |
| `country`               | string | No        | Max 100                                 |
| `language`              | string | No        | Max 10                                  |
| `timezone`              | string | No        | Max 50                                  |

**Response — `201 Created`:**

```json
{
  "success": true,
  "message": "Registration successful.",
  "data": {
    "user": {
      "id": 1, "username": "johndoe", "email": "john@example.com",
      "first_name": "John", "last_name": "Doe", "is_active": true,
      "is_admin": false, "role": "client", "max_connections": 1,
      "m3u_token": "x7y8z9...", "created_at": "2026-09-21T12:00:00.000000Z",
      "profile": { "id": 1, "country": "US", "language": "en", "timezone": "UTC" }
    },
    "token": "1|abc123def456..."
  }
}
```

#### POST `/api/v1/auth/login` — Login

**Request:**

| Parameter | Type   | Required | Description         |
|-----------|--------|----------|---------------------|
| `username` | string | Yes     | Username or email   |
| `password` | string | Yes     | Account password    |

**Response — `200`:**

```json
{
  "success": true,
  "message": "Login successful.",
  "data": {
    "user": { /* User with profile */ },
    "token": "2|def456ghi789..."
  }
}
```

#### POST `/api/v1/auth/logout` — Logout

**Headers:** `Authorization: Bearer <sanctum-token>`

Revokes the current Sanctum token. Returns `200` JSON or redirects to `/`.

#### GET `/api/v1/auth/me` — Current User

**Headers:** `Authorization: Bearer <sanctum-token>`

Returns the authenticated user with `profile` and `subscriptions.subscriptionPackage`
eager-loaded.

---

### 5.2 Channels

These endpoints are **public** (no auth required) — they list active channels.

#### GET `/api/v1/channels` — List Channels

| Query Parameter | Type   | Description                              | Default |
|-----------------|--------|------------------------------------------------------|---------|
| `category`      | string | Filter by category slug                              | —       |
| `search`        | string | Full-text search on `name` / `description`            | —       |
| `per_page`      | int    | Items per page                                       | `20`    |
| `page`          | int    | Page number                                          | `1`     |

Orders by `sort_order` ASC, then `name` ASC. Returns a Laravel paginator.

**Response — `200`:**

```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 5, "name": "CNN International", "slug": "cnn-international",
        "channel_number": 5, "description": "24-hour news channel",
        "logo_url": "https://cdn.example.com/logos/cnn.png",
        "stream_url": "udp://@239.1.1.1:1234", "stream_type": "hls",
        "source_type": "multicast", "epg_channel_id": "cnn.intl",
        "is_active": true, "is_free": false, "quality": "hd",
        "sort_order": 5, "source_status": "online",
        "categories": [ { "id": 1, "name": "News", "slug": "news" } ],
        "epg_programs": [ /* up to 20 programs */ ]
      }
    ],
    "links": { /* pagination links */ },
    "meta": { /* total, per_page, etc. */ }
  }
}
```

#### GET `/api/v1/channels/categories` — Channel Categories

Returns all active `ContentCategory` records with at least one channel, ordered
by `sort_order`. Each category includes `channels_count`.

**Response — `200`:**

```json
{ "success": true, "data": { "categories": [ { "id": 1, "name": "News", "slug": "news", "sort_order": 0, "is_active": true, "channels_count": 8 } ] } }
```

#### GET `/api/v1/channels/{channel}` — Channel Detail

Matches either `slug` or numeric `id`. Returns the channel with `categories`
and up to 20 `epgPrograms` (programs from last 2 hours, ordered by start time).

**Errors:** `404` — `{ "success": false, "message": "Channel not found." }`

---

### 5.3 VOD Content

All VOD endpoints are **public** (no auth required).

#### GET `/api/v1/vod` — List VOD Content

| Query Parameter | Type    | Description                              |
|-----------------|---------|------------------------------------------|
| `category`      | string  | Filter by category slug                  |
| `genre`         | string  | Filter where JSON `genre` array contains value |
| `year`          | int     | Filter by release year                   |
| `rating_min`    | float   | Minimum rating                           |
| `rating_max`    | float   | Maximum rating                           |
| `type`          | string  | `movie` or `series`                      |
| `search`        | string  | Full-text search on title/description/genre/cast |
| `per_page`      | int     | Default 20                               |
| `page`          | int     | Page number                              |

Orders by `created_at DESC`. Returns a Laravel paginator.

#### GET `/api/v1/vod/categories` — VOD Categories

Returns categories with `vod_content_count`.

#### GET `/api/v1/vod/genres` — VOD Genres

Returns an array of distinct genre strings across all active VOD content.

#### GET `/api/v1/vod/latest` — Latest VOD

Paginated list ordered by `created_at DESC`.

#### GET `/api/v1/vod/featured` — Featured VOD

Paginated list of content where `is_featured = true`, ordered by `featured_order`.

#### GET `/api/v1/vod/search` — Search VOD

| Query Parameter | Type   | Required | Description        |
|-----------------|--------|----------|----------------------|
| `query`         | string | Yes       | Search term         |
| `per_page`      | int    | No        | Default 20           |

Searches `title`, `description`, JSON `genre`, and JSON `cast`.

#### GET `/api/v1/vod/{vod}` — VOD Detail

Matches `slug` or `id`. Returns content with `categories`, `vodMedia`, and the
10 most recent approved `reviews` (with `user`). **Also increments `view_count`.**

```json
{
  "success": true,
  "data": {
    "content": {
      "id": 42, "title": "The Grand Tour", "slug": "the-grand-tour",
      "year": 2016, "imdb_id": "tt5701624", "tmdb_id": "81322",
      "rating": 8.500, "poster_url": "https://...", "backdrop_url": "https://...",
      "trailer_url": "https://youtube.com/...", "type": "series",
      "duration": 420, "cast": ["Jeremy Clarkson", "Richard Hammond", "James May"],
      "genre": ["Comedy", "Automotive"], "is_active": true, "is_featured": true,
      "season_count": 4, "episode_count": 42, "view_count": 1280,
      "categories": [ { "id": 6, "name": "Comedy", "slug": "comedy" } ],
      "vod_media": [ /* media files */ ],
      "reviews": [ /* approved reviews with user */ ]
    }
  }
}
```

**Error — `404`:** `{ "success": false, "message": "VOD content not found." }`

#### GET `/api/v1/vod/{vod}/similar` — Similar VOD

Returns up to 10 active VOD items sharing at least one category.

#### GET `/api/v1/vod/{vod}/seasons` — VOD Seasons

Only valid for `type = series`. Returns the VOD with `vodMedia` (episode files)
ordered by quality. **Error — `400`:** not a series.

---

### 5.4 EPG

All EPG endpoints are **public** (no auth required).

#### GET `/api/v1/epg/{channel}` — Channel EPG

| Parameter | Type   | Position | Description                                           |
|-----------|--------|----------|-------------------------------------------------------|
| `channel` | mixed  | path     | Channel ID or slug                                    |
| `date`    | string | query    | Specific date (`Y-m-d`); if omitted, returns programs from last 2h onward |

```json
{
  "success": true,
  "data": {
    "channel": { /* Channel model */ },
    "programs": [
      {
        "id": 1, "channel_id": 5, "title": "CNN Newsroom",
        "description": "Live breaking news.",
        "start_time": "2026-09-21T12:00:00.000000Z",
        "end_time": "2026-09-21T13:00:00.000000Z",
        "program_id": "e452", "language": "en", "rating": "PG",
        "category": "News", "episode": "1", "season": "1"
      }
    ]
  }
}
```

#### GET `/api/v1/epg` — Programs by Date Range

| Query Parameter | Type | Required | Description                    |
|-----------------|------|----------|--------------------------------|
| `start_date`    | date | Yes       | Start of range (`Y-m-d`)   |
| `end_date`      | date | Yes       | End of range (must be ≥ start_date) |
| `channel_id`    | int  | No        | Filter to one channel        |
| `per_page`      | int  | No        | Default 100                  |

#### GET `/api/v1/epg/current` — Currently Airing

Returns programs where `start_time <= now <= end_time`. Accepts optional
`channel_id` filter.

#### GET `/api/v1/epg/upcoming` — Upcoming Programs

Returns programs where `start_time > now`, ordered ascending.
`limit` defaults to 50.

---

### 5.5 Profile (License-Protected)

> Requires `Authorization: Bearer <jwt>` (license JWT). The middleware injects
> `license` and `device` into the request.

#### GET `/api/v1/user/profile` — Get Profile

Returns hotel/device context derived from the license JWT.

```json
{
  "success": true,
  "data": {
    "hotel": {
      "hotel_id": "hotel-001",
      "hotel_name": "Grand Plaza Hotel",
      "license_type": "premium",
      "features": { /* feature array */ }
    },
    "device": {
      "id": 42, "name": "Lobby TV", "type": "android_tv",
      "model": "NVIDIA Shield TV Pro",
      "os": "Android TV 11",
      "app_version": "2.1.0",
      "last_seen_at": "2026-09-21T13:00:00.000000Z"
    }
  }
}
```

#### PUT `/api/v1/user/profile` — Update Device Profile

| Parameter      | Type   | Required | Description              |
|----------------|--------|----------|--------------------------|
| `device_name`  | string | No       | Friendly device name     |
| `device_model` | string | No       | Device model             |
| `app_version`  | string | No       | App version string       |
| `mac_address`  | string | No       | MAC address              |

---

### 5.6 Favorites (License-Protected)

> Requires `Authorization: Bearer <jwt>`.

#### GET `/api/v1/favorites` — List Favorites

| Parameter   | Type | Default | Description |
|-------------|------|---------|-------------|
| `per_page`  | int  | 20      | Pagination   |

Eager-loads `vodContent` and `channel`.

#### POST `/api/v1/favorites/store` — Add Favorite

| Parameter         | Type | Required | Rule                                             |
|-------------------|------|----------|--------------------------------------------------|
| `vod_content_id`  | int  | One of two required | Must exist in `vod_contents`          |
| `channel_id`      | int  | One of two required | Must exist in `channels`             |

**Response — `201`:**

```json
{ "success": true, "message": "Added to favorites.", "data": { "favorite": { "id": 7, "user_id": 1, "vod_content_id": null, "channel_id": 5 } } }
```

**Conflict — `409`:** `{ "success": false, "message": "This item is already in your favorites." }`

#### DELETE `/api/v1/favorites/{id}` — Remove Favorite

Deletes a favorite owned by the current user. **Response — `200`:**
`{ "success": true, "message": "Removed from favorites." }`

---

### 5.7 Watch History (License-Protected)

#### GET `/api/v1/watch-history` — List Watch History

| Parameter | Type   | Description                                    | Default |
|-----------|--------|------------------------------------------------|---------|
| `type`    | string | `channel` or `vod` — filter by content type  | —       |
| `per_page`| int    | Pagination                                     | 20      |

Eager-loads `channel` and `vodContent`.

#### POST `/api/v1/watch-history` — Record/Update Watch Session

Uses `updateOrCreate` on `(user_id, channel_id, vod_content_id)`.

| Parameter          | Type    | Required | Description                          |
|--------------------|---------|----------|--------------------------------------|
| `channel_id`       | int     | One of two required | Channel ID              |
| `vod_content_id`   | int     | One of two required | VOD content ID          |
| `duration_watched` | int     | No       | Seconds watched                      |
| `progress`         | float   | No       | Percentage (0–100)                   |
| `completed`        | boolean | No       | Whether content was fully watched    |

**Response — `201`:**

```json
{ "success": true, "message": "Watch history saved.", "data": { "history": { /* ... */ } } }
```

#### PUT `/api/v1/watch-history/{id}` — Update Entry

| Parameter          | Type    | Required |
|--------------------|---------|----------|
| `duration_watched` | int     | No       |
| `progress`         | float   | No       |
| `completed`        | boolean | No       |

---

### 5.8 Reviews (License-Protected)

#### GET `/api/v1/reviews/vod/{vod}` — List Reviews for VOD

Verifies the VOD exists and is active. Returns approved reviews with author
`user`, plus an `average_rating`.

**Response — `200`:**

```json
{
  "success": true,
  "data": {
    "reviews": [ /* paginated approved UserReview with user */ ],
    "average_rating": 8.3
  }
}
```

#### POST `/api/v1/reviews` — Create / Update Review

If the user has already reviewed this VOD, the existing review is **updated**
(idempotent). New reviews are created with `is_approved = false` (awaiting
moderation).

| Parameter        | Type  | Required | Rule                          |
|------------------|-------|----------|-------------------------------|
| `vod_content_id` | int   | Yes       | Must exist in `vod_contents` |
| `rating`         | float | Yes       | 1.0 – 10.0                   |
| `title`          | string| No        | Max 255                      |
| `review`         | string| No        | Free text                    |

**Response — `201`** (new) or **`200`** (update):

```json
{ "success": true, "message": "Review submitted successfully.", "data": { "review": { /* ... */ } } }
```

---

### 5.9 Subscriptions (License-Protected)

#### GET `/api/v1/subscription` or `/api/v1/subscription/current`

Returns the user's active subscription (status `active`, `end_date >= now`),
eager-loaded with `subscriptionPackage`. Returns `null` if none active.

```json
{
  "success": true,
  "data": {
    "subscription": {
      "id": 10, "user_id": 1, "subscription_package_id": 2,
      "status": "active",
      "start_date": "2026-09-01T00:00:00.000000Z",
      "end_date": "2026-10-01T00:00:00.000000Z",
      "auto_renew": true,
      "payment_reference": "INV-ABC123",
      "subscription_package": { "id": 2, "name": "Premium Monthly", "price": "9.99", "duration_days": 30 }
    }
  }
}
```

#### GET `/api/v1/subscription/packages` — Available Packages

```json
{ "success": true, "data": [ { "id": 1, "name": "Basic Monthly", "price": "4.99", "duration_days": 30 } ] }
```

#### POST `/api/v1/subscription/subscribe` — Subscribe to a Package

| Parameter            | Type    | Required | Rule                                  |
|----------------------|---------|----------|---------------------------------------|
| `package_id`         | int     | Yes       | Must exist in `subscription_packages` |
| `payment_method_id`  | int     | Yes       | Must exist in `payment_methods`       |
| `auto_renew`         | boolean | No        |                                       |

**Response — `201`:**

```json
{
  "success": true,
  "message": "Subscription created successfully.",
  "data": {
    "subscription": { /* Subscription with package */ },
    "invoice": { /* Invoice */ },
    "client_secret": "pi_123_secret_..."
  }
}
```

> Alias: `POST /api/v1/subscription/{package}/subscribe` uses path param `package`
> instead of body `package_id`.

#### POST `/api/v1/subscription/renew` — Renew Subscription

| Parameter            | Type | Required | Rule                                       |
|----------------------|------|----------|--------------------------------------------|
| `subscription_id`    | int  | Yes       | Must belong to the current user            |
| `payment_method_id`  | int  | Yes       | Must exist in `payment_methods`            |

Extends from current/new `end_date` by the package's `duration_days`.

#### GET `/api/v1/subscription/history` — Subscription History

Paginated `SubscriptionHistory` entries for the user's subscriptions.

---

### 5.10 Payments (License-Protected)

#### GET `/api/v1/payment/methods` — List Payment Methods

Returns active `PaymentMethod` records ordered by `sort_order`. The `config`
field is **hidden** from the response.

```json
{ "success": true, "data": { "methods": [ { "id": 1, "name": "Credit Card", "slug": "credit_card", "gateway": "stripe", "is_active": true } ] } }
```

#### POST `/api/v1/payment/invoice` — Create Invoice

Creates a pending invoice for a subscription package. Applies a **fixed 10% tax**.

| Parameter            | Type | Required | Rule                              |
|----------------------|------|----------|-----------------------------------|
| `package_id`         | int  | Yes       | Must exist                      |
| `payment_method_id`  | int  | Yes       | Must exist                      |

**Response — `201`:**

```json
{
  "success": true, "message": "Invoice created successfully.",
  "data": {
    "invoice": {
      "id": 30, "invoice_number": "INV-A1B2C3D4",
      "subtotal": "4.99", "tax": "0.50", "total": "5.49",
      "status": "pending", "paid_at": null,
      "items": [ { "description": "Basic Monthly - monthly", "unit_price": "4.99" } ],
      "payment_method": { /* method (config hidden) */ }
    }
  }
}
```

#### POST `/api/v1/payment/pay/{invoice}` — Pay Invoice

Marks the invoice as paid, creates an active subscription, and increments the
user's `max_connections` to the package's value.

| Parameter            | Type   | Required | Description                    |
|----------------------|--------|----------|--------------------------------|
| `payment_reference`  | string | No       | External payment reference     |

**Response — `200`:**

```json
{ "success": true, "message": "Payment processed successfully.", "data": { "invoice": { /* paid invoice */ } } }
```

| Status | Condition                              |
|--------|------------------------------------------|
| 400    | Invoice already paid or cancelled        |
| 404    | Invoice not found                        |

#### GET `/api/v1/payment/invoices` — List My Invoices

Paginated list ordered by `created_at DESC`, with items and payment method.

---

### 5.11 Admin Servers (License-Protected)

#### GET `/api/v1/admin/servers` — Streaming Servers

Returns all `StreamingServer` records ordered by name.

```json
{
  "success": true, "data": [
    { "id": 1, "name": "Primary HLS Server", "host": "192.168.50.254",
      "port": 25460, "protocol": "hls", "is_active": true,
      "max_connections": 500, "current_connections": 0,
      "location": "Datacenter A", "provider": "Nginx" }
  ]
}
```

---

## 6. Xtream Codes API

The Xtream Codes protocol is the standard interface used by IPTV players
(TiviMate, XCIPTV, GSE Smart IPTV, IPTV Smarters, Formuler, etc.) to authenticate
and fetch channel/VOD/series lists and EPG data.

**Base URL:** `https://<server>:25460/player_api.php`

All requests use **HTTP Basic auth** via query-string `username` + `password`:

```
GET /player_api.php?action=<action>&username=<username>&password=<password>&[extra_params]
```

Authentication accepts either the user's login password or their `m3u_token`.
If authentication fails, returns `401` with `{"user_info": null, "server_info": null}`.

### Actions

| Action                | Auth | Query Parameters                          | Returns              |
|-----------------------|------|-------------------------------------------|----------------------|
| `auth`                | Yes  | `username`, `password`                    | `user_info` + `server_info` |
| `get_live_streams`    | Yes  | `username`, `password`, `cat_id` (opt)    | Array of live streams |
| `get_vod_streams`     | Yes  | `username`, `password`, `cat_id` (opt)    | Array of VOD movies   |
| `get_series`          | Yes  | `username`, `password`, `cat_id` (opt)    | Array of series       |
| `get_live_categories` | Yes  | `username`, `password`                    | Array of categories   |
| `get_vod_categories`  | Yes  | `username`, `password`                    | Array of categories   |
| `get_series_categories`| Yes | `username`, `password`                    | Array of categories   |
| `get_epg_streams`     | Yes  | `username`, `password`, `stream_id`       | `epg_listings` array  |
| `get_vod_info`        | Yes  | `username`, `password`, `vod_id`          | `info` + `movie_data` |
| `get_series_info`     | Yes  | `username`, `password`, `series_id`       | `seasons` + `episodes`|

### Example: `auth`

```
GET /player_api.php?action=auth&username=johndoe&password=x7y8z9...
```

```json
{
  "user_info": {
    "username": "johndoe", "password": "", "message": "",
    "auth": 1, "status": "Active",
    "exp_date": "1757846400",
    "is_trial": "0", "active_cons": "0",
    "created_at": "1755698400",
    "max_connections": "2",
    "allowed_output_formats": ["m3u8", "ts", "rtmp"]
  },
  "server_info": {
    "url": "192.168.50.254", "port": "25460", "https_port": "443",
    "server_protocol": "http", "rtmp_port": "1935",
    "timezone": "UTC", "timestamp_now": 1755852000,
    "time_now": "2026-09-21 13:00:00", "process": true
  }
}
```

If authentication fails, returns `401` with `{"user_info": null, "server_info": null}`.

### Stream Entry Fields

**Live streams** (`get_live_streams`):

| Field                 | Description                              |
|-----------------------|------------------------------------------|
| `num`                 | Channel number                           |
| `name`                | Channel display name                     |
| `stream_type`         | Always `"live"`                          |
| `stream_id`           | Channel ID (regular) or `id + 9000000` (admin channel) |
| `stream_icon`         | Logo URL                                 |
| `epg_channel_id`      | EPG program identifier                   |
| `added`               | Unix timestamp                           |
| `category_id`         | Category ID                              |

**VOD streams** (`get_vod_streams`):

| Field                 | Description                              |
|-----------------------|------------------------------------------|
| `num`                 | Content ID                               |
| `name`                | Title                                    |
| `stream_type`         | `"movie"`                                |
| `stream_id`           | VOD content ID                           |
| `stream_icon`         | Poster URL                               |
| `rating_5based`       | Rating scaled to 0–5                     |
| `container_extension` | `mp4` (default)                          |

### EPG (`get_epg_streams`)

Returns `{"epg_listings": [...]}` — program listings for a channel, base64-encoded
titles/descriptions, ordered by `start_time`. Limited to 10 upcoming programs.

### VOD Info (`get_vod_info`)

Returns `{"info": {...}, "movie_data": {...}}` with metadata, cast, director,
ratings, trailer, and container extension.

### Series Info (`get_series_info`)

Returns `{"seasons": [...], "info": {...}, "episodes": {...}}`.
Episodes are keyed by season number (string): `{"1": [...], "2": [...]}`.

---

## 7. Streaming Playback URLs

The streaming engine uses URL patterns compatible with Xtream Codes clients.
All stream requests are authenticated via URL-encoded `username` / `password`.

### Live TV — HLS (primary)

```
GET /{username}/{password}/live/{stream_id}.m3u8
GET /{username}/{password}/{stream_id}.m3u8
```

The server authenticates the request, enforces connection limits, ensures a
background FFmpeg ingest is running for the channel, then serves the HLS playlist
via `X-Accel-Redirect` (Nginx). The player then fetches segments:

```
GET /{username}/{password}/live/{stream_id}/segment_{N}.ts
```

Segments are also served directly via:

```
GET /hls/{channel_id}/{file}    (.m3u8 or .ts)
```

### Live TV — HTTP-TS (MPEG-TS over HTTP)

```
GET /ts/{username}/{password}/{stream_id}
```

Streams MPEG-TS segments as a continuous byte stream. Faster channel zap than HLS
(no playlist parsing). Segments are read from the local ingest directory.

### VOD Movies

```
GET /{username}/{password}/vod/{stream_id}.{extension}
GET /movie/{username}/{password}/{stream_id}
```

Served as native MP4 byte-range streams, supporting instant seeking/pause/
scrubbing via `ngx_http_mp4_module`. The `{extension}` must match the media's
`container_extension`.

### Series Episodes

```
GET /{username}/{password}/series/{media_id}.{extension}
GET /series/{username}/{password}/{stream_id}
```

The `{media_id}` is the `vod_media.id` (episode-level ID), not the series
`vod_content.id`.

### M3U Playlist (full)

```
GET /get.php?username=<username>&password=<password>
Content-Type: application/x-mpegurl
```

Generates a complete M3U playlist with all active channels and VOD movies.
Channels are filtered by the user's assigned bouquets (or all channels if no
bouquets are assigned).

### XMLTV (EPG)

```
GET /xmltv.php?username=<username>&password=<username>
Content-Type: application/xml
Cache-Control: max-age=3600
```

Returns the next 24 hours of EPG data in XMLTV format.

### Token-based M3U (per-client)

```
GET /playlist/{token}/m3u
```

Generates an M3U playlist for a client using their `m3u_token` (no password in URL).

### HMS Public Channel List

```
GET /api/v1/hms/channels
```

A lightweight public endpoint that returns just `id`, `name`, `channel_number`,
and `logo_url` for all active channels. Used by HMS systems to populate their
default channel picker. No authentication required.

```json
{ "success": true, "channels": [ { "id": 5, "name": "CNN", "channel_number": 5, "logo_url": "https://..." } ] }
```

---

## 8. Data Models (Response Schemas)

### User (Sanctum-authenticated)

| Field              | Type    | Description                              |
|--------------------|---------|------------------------------------------|
| `id`               | int     | Primary key                              |
| `username`         | string  | Login username                           |
| `email`            | string  | Email address                            |
| `password`         | hidden  | Never returned in responses              |
| `first_name`       | string  |                                          |
| `last_name`        | string  |                                          |
| `phone`            | string\|null |                                      |
| `is_active`        | boolean |                                          |
| `is_admin`         | boolean |                                          |
| `role`             | string  | `client`, `reseller`, `admin`, `super_admin` |
| `is_reseller`      | boolean |                                          |
| `max_connections`  | int     | Concurrent stream limit                  |
| `m3u_token`        | string  | Xtream streaming token                   |
| `created_at`       | datetime |                                         |

### Channel

| Field                | Type        | Description                              |
|----------------------|-------------|------------------------------------------|
| `id`                 | int         |                                          |
| `name`               | string      | Display name                             |
| `slug`               | string      | URL-friendly identifier                  |
| `channel_number`     | int         | EPG channel number                       |
| `description`        | string\|null |                                      |
| `logo_url`           | string\|null |                                          |
| `stream_url`         | string\|null | Source URL (multicast, HLS, etc.)      |
| `stream_type`        | string      | `hls`, `mpegts`, etc.                    |
| `source_type`        | string      | `multicast`, `youtube`, `file`, etc.     |
| `epg_channel_id`     | string\|null | EPG program identifier                   |
| `is_active`          | boolean     |                                          |
| `is_free`            | boolean     | Free (no subscription) or premium        |
| `quality`            | string\|null | `sd`, `hd`, `full_hd`, `uhd`           |
| `sort_order`         | int         |                                          |
| `source_status`      | string      | `online`, `offline`, `checking`          |
| `categories`         | array       | Related ContentCategory records          |
| `epg_programs`       | array       | Related EPGProgram records               |

### VODContent

| Field            | Type        | Description                              |
|------------------|-------------|------------------------------------------|
| `id`             | int         |                                          |
| `title`          | string      |                                          |
| `original_title` | string\|null |                                      |
| `slug`           | string      |                                          |
| `description`    | text\|null   |                                          |
| `year`           | int         | Release year                             |
| `imdb_id`        | string\|null |                                          |
| `tmdb_id`        | string\|null |                                          |
| `rating`         | float       | 0–10 scale                               |
| `poster_url`     | string\|null |                                          |
| `backdrop_url`   | string\|null |                                          |
| `trailer_url`    | string\|null | YouTube trailer URL                      |
| `type`           | string      | `movie` or `series`                      |
| `duration`       | int         | In seconds                               |
| `cast`           | array\|null | JSON array of actor names                |
| `genre`          | array\|null | JSON array of genre strings              |
| `is_active`      | boolean     |                                          |
| `is_featured`    | boolean     |                                          |
| `season_count`   | int         | For series                               |
| `episode_count`  | int         | For series                               |
| `view_count`     | int         |                                          |
| `categories`     | array       | Related ContentCategory records          |
| `vod_media`      | array       | Related VODMedia files                   |

### EPGProgram

| Field         | Type        | Description                              |
|---------------|-------------|------------------------------------------|
| `id`          | int         |                                          |
| `channel_id`  | int         | Foreign key to Channel                   |
| `title`       | string      | Program title                            |
| `start_time`  | datetime    | ISO 8601                                 |
| `end_time`    | datetime    | ISO 8601                                 |
| `description` | string\|null |                                      |
| `program_id`  | string\|null | External program ID                     |
| `language`    | string\|null |                                      |
| `rating`      | string\|null |                                      |
| `category`    | string\|null |                                      |
| `episode`     | string\|null |                                      |
| `season`      | string\|null |                                      |

### SubscriptionPackage

| Field              | Type    | Description                    |
|--------------------|---------|--------------------------------|
| `id`               | int     |                                |
| `name`             | string  | Display name                   |
| `slug`             | string  | URL-friendly identifier        |
| `price`            | decimal | e.g. `9.99`                    |
| `billing_cycle`    | string  | `monthly`, `yearly`            |
| `duration_days`    | int     | Subscription length            |
| `max_connections`  | int     | Concurrent streams allowed     |
| `is_active`        | boolean |                                |
| `sort_order`       | int     |                                |

### Subscription

| Field                   | Type      | Description                            |
|-------------------------|-----------|----------------------------------------|
| `id`                    | int       |                                        |
| `user_id`               | int       |                                        |
| `subscription_package_id`| int      |                                        |
| `status`                | string    | active, expired, cancelled, pending    |
| `start_date`            | datetime  |                                        |
| `end_date`              | datetime  |                                        |
| `auto_renew`            | boolean   |                                        |
| `payment_reference`     | string\|null | External payment reference        |

### EPGProgram

| Field         | Type        | Description                              |
|---------------|-------------|------------------------------------------|
| `id`          | int         |                                          |
| `channel_id`  | int         | Foreign key to Channel                   |
| `title`       | string      | Program title                            |
| `description` | string\|null |                                      |
| `start_time`  | datetime    | ISO 8601                                 |
| `end_time`    | datetime    | ISO 8601                                 |
| `program_id`  | string\|null | External program ID                     |
| `language`    | string\|null |                                      |
| `rating`      | string\|null | Content rating (e.g. "PG")              |
| `category`    | string\|null |                                      |
| `episode`     | string\|null |                                      |
| `season`      | string\|null |                                      |

### SubscriptionPackage

| Field              | Type    | Description                    |
|--------------------|---------|--------------------------------|
| `id`               | int     |                                |
| `name`             | string  | Display name                   |
| `slug`             | string  | URL-friendly identifier        |
| `description`      | string\|null |                            |
| `price`            | decimal | e.g. `9.99`                    |
| `billing_cycle`    | string  | `monthly`, `yearly`, etc.      |
| `duration_days`    | int     | Subscription length            |
| `never_expire`     | boolean |                                |
| `features`         | array   | Feature flags                  |
| `max_connections`  | int     | Concurrent streams allowed     |
| `is_active`        | boolean |                                |
| `sort_order`       | int     |                                |

### Subscription

| Field                   | Type      | Description                    |
|-------------------------|-----------|--------------------------------|
| `id`                    | int       |                                |
| `user_id`               | int       |                                |
| `subscription_package_id`| int      |                                |
| `status`                | string    | `active`, `expired`, `cancelled`, `pending` |
| `start_date`            | datetime  |                                |
| `end_date`              | datetime  |                                |
| `auto_renew`            | boolean   |                                |
| `payment_reference`     | string\|null | External payment reference  |

### Invoice

| Field                | Type        | Description                    |
|----------------------|-------------|--------------------------------|
| `id`                 | int         |                                |
| `user_id`            | int         |                                |
| `payment_method_id`  | int         |                                |
| `invoice_number`     | string      | e.g. `INV-A1B2C3D4`           |
| `subtotal`           | decimal     |                                |
| `tax`                | decimal     | 10% of subtotal                |
| `total`              | decimal     | subtotal + tax                 |
| `status`             | string      | `pending`, `paid`, `cancelled` |
| `paid_at`            | datetime\|null |                             |
| `payment_reference`  | string\|null |                              |
| `notes`              | string\|null |                              |
| `items`              | array       | InvoiceItem records (rel.)     |
| `payment_method`     | object      | PaymentMethod (rel.)           |

### InvoiceItem

| Field                     | Type    | Description                    |
|---------------------------|---------|--------------------------------|
| `id`                      | int     |                                |
| `invoice_id`              | int     |                                |
| `subscription_package_id` | int     |                                |
| `description`             | string  |                                |
| `quantity`                | int     |                                |
| `unit_price`              | decimal |                                |
| `total_price`             | decimal |                                |