# Fix Summary — 405 Error on Dashboard "Restart Ingest"

## Problem

Clicking the per-channel **restart (↻)** button in the Admin Dashboard's *HLS Ingests* panel produced an **HTTP 405 – Method Not Allowed**. The ingest was never restarted.

## Root Cause

The dashboard action was submitted with the **POST** verb to a target that was not registered as a POST route. Because the intended route name did not resolve to a POST endpoint (route-name ambiguity among the many `channels.*` routes across the admin and client groups), the request landed on a URL that only accepts other HTTP verbs — Laravel responds to that mismatch with `405 Method Not Allowed`.

## Changes Made

### 1. `routes/web.php` — dedicated, uniquely-named POST route

Added inside the `admin` middleware group (prefix `/admin`, name prefix `admin.`):

```php
// ─── Channels ─────────────────────────────────────────────────────
Route::post('/channels/{channel}/refresh-ingest', [\App\Http\Controllers\Admin\DashboardController::class, 'refreshIngest'])
    ->name('channels.refresh-ingest');
```

- Full route name resolves to **`admin.channels.refresh-ingest`** — verified unique; it no longer collides with any other `channels.*` route name (admin panel routes use `admin.channels.*`, client routes use `client.channels.*`).
- Registered explicitly as **POST**, so the verb now matches what the frontend sends.

### 2. `resources/js/Pages/Admin/Dashboard/Index.vue` — form/action uses the correct route

Each ingest row now renders a restart button wired through Inertia to the exact named route:

```js
import { Link, router } from '@inertiajs/vue3'

const refreshing = ref(null)
const refreshIngest = (id) => {
  refreshing.value = id
  router.post(route('admin.channels.refresh-ingest', { channel: id }), {}, {
    preserveScroll: true,
    preserveState: true,
    onFinish: () => { refreshing.value = null },
    onSuccess: () => router.reload({ only: ['stats'] }),
  })
}
```

```html
<button @click="refreshIngest(ing.id)" :disabled="refreshing === ing.id"
        class="p-1.5 rounded-md text-gray-400 hover:text-white hover:bg-gray-600 transition disabled:opacity-50">
  <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': refreshing === ing.id }" />
</button>
```

Key points:
- Uses `router.post(...)` (POST) against a POST-registered route → verb/method now agree.
- References the route **by name** (`admin.channels.refresh-ingest`) instead of a hard-coded URL, so future path changes can't silently break it.
- Spins the icon while in-flight, preserves scroll/state, and reloads only the `stats` prop on success.

### 3. `app/Http/Controllers/Admin/DashboardController.php` — backing handler

```php
public function refreshIngest(Request $request, Channel $channel, XtreamController $xtream): JsonResponse|RedirectResponse
{
    abort_unless($channel->is_active, 404);

    $xtream->restartHlsStream($channel);

    if ($request->expectsJson()) {
        return response()->json(['success' => true, 'channel_id' => $channel->id]);
    }

    return back()->with('success', "Ingest for '{$channel->name}' restarted.");
}
```

Supports both JSON (AJAX-style) and standard redirect responses, with implicit route-model binding on `{channel}`.

## Resulting Flow

1. User clicks ↻ on an ingest row → `POST /admin/channels/{id}/refresh-ingest`.
2. Route `admin.channels.refresh-ingest` matches (unique name, POST verb) → auth + license + admin middleware apply.
3. `DashboardController@refreshIngest` restarts the channel's HLS stream and flashes success.
4. Dashboard reloads `stats` only; the ingest status dot reflects the restart.

## Verification Performed

- Audited all route names in `routes/web.php`: the fully-qualified names (after `admin.` / `client.` group prefixes) are unique for every touched route; `channels.refresh-ingest` appears exactly once.
- Confirmed the frontend references `admin.channels.refresh-ingest`, matching the backend registration exactly.
- Confirmed the controller method exists with the expected signature and dual JSON/redirect responses.

## Follow-up Recommendations (not part of this fix)

1. **Unreachable route:** `GET /channels/admin/{channel}/sweep` (`admin.channels.scan-multicast`) is registered *after* the `/{any}` catch-all at the bottom of `routes/web.php`, so it can never match. Move it above the catch-all.
2. **Double-prefixed names:** several admin-channel routes hardcode `admin.` in their names while already sitting in the `admin.`-prefixed group (yielding `admin.admin.channels.*`). Harmless today since the frontend uses the same strings, but worth normalizing.
3. Remember to clear config/route caches on deploy (`php artisan route:clear && php artisan config:clear`) so the new route is picked up.