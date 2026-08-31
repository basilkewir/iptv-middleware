<?php

use App\Http\Controllers\Admin\BouquetController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ChannelController;
use App\Http\Controllers\Admin\AdminChannelController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EpgController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\ServerController;
use App\Http\Controllers\Admin\QualityDetectionController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TranscodingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VODController;
use App\Http\Controllers\HlsController;

use App\Models\Channel;
use App\Models\ContentCategory;
use App\Models\Notification;
use App\Models\SubscriptionPackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| Admin + Reseller panel only. No public-facing client dashboard.
| Xtream Codes API remains public for IPTV player compatibility.
*/

// ─── Guest Routes ──────────────────────────────────────────────────────────────
// Public HLS playback (no session auth — consumed directly by the video player).
// Serves playlists/segments already written to storage by the playout engine.
Route::get('/hls/{key}/{file}', [HlsController::class, 'serve'])
    ->where('key', '[^/]+')
    ->where('file', '.*')
    ->name('hls.serve');

// ─── License Activation (accessible even when no valid license exists) ─────────
Route::middleware('web')->group(function () {
    Route::get('/license-required', fn () => Inertia::render('Auth/LicenseRequired'))
        ->name('license.required');

    Route::post('/license/activate', function (\Illuminate\Http\Request $request) {
        $validated = $request->validate([
            'license_key' => 'required|string',
        ]);

        $license = \App\Models\License::where('license_key', trim($validated['license_key']))->first();

        if (! $license) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'license_key' => 'This license key is invalid, expired, or inactive.',
            ]);
        }

        // A suspended license can be recovered by re-entering its correct key.
        if ($license->status === \App\Models\License::STATUS_SUSPENDED) {
            $license->update(['status' => \App\Models\License::STATUS_ACTIVE]);
        }

        if (! $license->isValid()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'license_key' => 'This license key is invalid, expired, or inactive.',
            ]);
        }

        return redirect()->route('login')->with('success', 'License activated successfully. You can now sign in.');
    })->name('license.activate');
});

Route::middleware(['web', 'guest', 'license.check'])->group(function () {
    Route::get('/', fn () => Inertia::render('Auth/Login'))->name('home');
    Route::get('/login', fn () => Inertia::render('Auth/Login'))->name('login');

    Route::post('/login', function (\Illuminate\Http\Request $request) {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = \App\Models\User::where('username', $credentials['username'])
            ->orWhere('email', $credentials['username'])
            ->first();

        if (! $user || ! \Illuminate\Support\Facades\Hash::check($credentials['password'], $user->password)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'username' => 'These credentials do not match our records.',
            ]);
        }

        if (! $user->is_active) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'general' => 'Your account has been deactivated.',
            ]);
        }

        if (! $user->is_admin && ! $user->is_reseller) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'general' => 'Access restricted to administrators and resellers only.',
            ]);
        }

        \Illuminate\Support\Facades\Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended('/admin/dashboard');
    });
});

// ─── Authenticated Logout ──────────────────────────────────────────────────────
Route::middleware(['auth:web', 'license.check'])->group(function () {
    Route::post('/logout', [App\Http\Controllers\Api\AuthController::class, 'logout'])->name('logout');
});

// ─── Dashboard redirect ──────────────────────────────────────────────────────
Route::middleware('auth:web')->get('/dashboard', fn () => redirect()->route('admin.dashboard'));

// ─── Admin Panel ───────────────────────────────────────────────────────────────
Route::middleware(['license.check', 'auth:web', \App\Http\Middleware\AdminMiddleware::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // ─── Users ────────────────────────────────────────────────────────
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', fn () => Inertia::render('Admin/Users/Create', [
            'packages' => SubscriptionPackage::where('is_active', true)->get(),
            'resellers' => \App\Models\User::where('is_reseller', true)->where('is_active', true)->get(),
            'bouquets' => \App\Models\Bouquet::where('is_active', true)->get(),
        ]))->name('users.create');
        Route::get('/users/bulk', fn () => Inertia::render('Admin/Users/BulkImport', [
            'packages' => SubscriptionPackage::where('is_active', true)->get(),
            'resellers' => \App\Models\User::where('is_reseller', true)->where('is_active', true)->get(),
        ]))->name('users.bulk');
        Route::post('/users/bulk', [UserController::class, 'bulkStore'])->name('users.bulk.store');
        Route::get('/users/bulk/template', [UserController::class, 'bulkTemplate'])->name('users.bulk.template');
        Route::get('/users/{user}/edit', [UserController::class, 'show'])->name('users.edit');
        Route::get('/users/{user}/activity', function (\App\Models\User $user) {
            return Inertia::render('Admin/Users/Activity', [
                'user' => $user,
                'activityLog' => $user->activityLogs()->latest()->take(50)->get(),
                'watchHistory' => $user->watchHistory()->latest()->take(50)->get(),
            ]);
        })->name('users.activity');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::post('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
        Route::post('/users/bulk-activate', [UserController::class, 'bulkActivate'])->name('users.bulk-activate');
        Route::post('/users/bulk-suspend', [UserController::class, 'bulkSuspend'])->name('users.bulk-suspend');
        Route::post('/users/bulk-delete', [UserController::class, 'bulkDelete'])->name('users.bulk-delete');

        // ─── Clients ──────────────────────────────────────────────────────
        Route::get('/clients', [\App\Http\Controllers\Admin\ClientController::class, 'index'])->name('clients.index');
        Route::get('/clients/create', [\App\Http\Controllers\Admin\ClientController::class, 'create'])->name('clients.create');
        Route::post('/clients', [\App\Http\Controllers\Admin\ClientController::class, 'store'])->name('clients.store');
        Route::get('/clients/{client}', [\App\Http\Controllers\Admin\ClientController::class, 'show'])->name('clients.show');
        Route::get('/clients/{client}/edit', [\App\Http\Controllers\Admin\ClientController::class, 'edit'])->name('clients.edit');
        Route::put('/clients/{client}', [\App\Http\Controllers\Admin\ClientController::class, 'update'])->name('clients.update');
        Route::delete('/clients/{client}', [\App\Http\Controllers\Admin\ClientController::class, 'destroy'])->name('clients.destroy');
        Route::post('/clients/{client}/toggle-status', [\App\Http\Controllers\Admin\ClientController::class, 'toggleStatus'])->name('clients.toggleStatus');
        Route::post('/clients/{client}/change-package', [\App\Http\Controllers\Admin\ClientController::class, 'changePackage'])->name('clients.changePackage');
        Route::post('/clients/{client}/extend-expiry', [\App\Http\Controllers\Admin\ClientController::class, 'extendExpiry'])->name('clients.extendExpiry');
        Route::post('/clients/{client}/reset-password', [\App\Http\Controllers\Admin\ClientController::class, 'resetPassword'])->name('clients.resetPassword');
        Route::post('/clients/{client}/generate-m3u', [\App\Http\Controllers\Admin\ClientController::class, 'generateM3u'])->name('clients.generateM3u');
        Route::post('/clients/{client}/send-credentials', [\App\Http\Controllers\Admin\ClientController::class, 'sendCredentials'])->name('clients.sendCredentials');
        Route::get('/clients/{client}/report', [\App\Http\Controllers\Admin\ClientController::class, 'report'])->name('clients.report');
        Route::get('/clients/bulk-import', [\App\Http\Controllers\Admin\ClientController::class, 'bulkImportForm'])->name('clients.bulkImportForm');
        Route::post('/clients/bulk-import', [\App\Http\Controllers\Admin\ClientController::class, 'bulkImport'])->name('clients.bulkImport');
        Route::get('/clients/bulk-template', [\App\Http\Controllers\Admin\ClientController::class, 'bulkTemplate'])->name('clients.bulkTemplate');
        Route::post('/clients/bulk-action', [\App\Http\Controllers\Admin\ClientController::class, 'bulkAction'])->name('clients.bulkAction');

        // ─── Admin Channels ───────────────────────────────────────────────
        Route::get('/channels/order', fn () => Inertia::render('Admin/Channels/Order'))->name('admin.channels.order');
        Route::get('/channels/all/list', [\App\Http\Controllers\Admin\ChannelController::class, 'allChannels'])->name('admin.channels.all');
        Route::put('/channels/reorder', [\App\Http\Controllers\Admin\ChannelController::class, 'reorder'])->name('admin.channels.reorder');
        Route::get('/channels/admin', [AdminChannelController::class, 'index'])->name('admin.channels.index');
        Route::get('/channels/admin/create', [AdminChannelController::class, 'create'])->name('admin.channels.create');
        Route::post('/channels/admin', [AdminChannelController::class, 'store'])->name('admin.channels.store');
        Route::get('/channels/admin/{channel}', [AdminChannelController::class, 'show'])->name('admin.channels.show');
        Route::get('/channels/admin/{channel}/edit', [AdminChannelController::class, 'edit'])->name('admin.channels.edit');
        Route::put('/channels/admin/{channel}', [AdminChannelController::class, 'update'])->name('admin.channels.update');
        Route::delete('/channels/admin/{channel}', [AdminChannelController::class, 'destroy'])->name('admin.channels.destroy');
        Route::post('/channels/admin/{channel}/toggle-status', [AdminChannelController::class, 'toggleStatus'])->name('admin.channels.toggle-status');
        Route::post('/channels/admin/{channel}/toggle-featured', [AdminChannelController::class, 'toggleFeatured'])->name('admin.channels.toggle-featured');
        Route::post('/channels/admin/{channel}/approve', [AdminChannelController::class, 'approve'])->name('admin.channels.approve');
        Route::post('/channels/admin/{channel}/test-stream', [AdminChannelController::class, 'testStream'])->name('admin.channels.test-stream');
        Route::post('/channels/admin/bulk-delete', [AdminChannelController::class, 'bulkDelete'])->name('admin.channels.bulk-delete');
        Route::post('/channels/admin/bulk-toggle-status', [AdminChannelController::class, 'bulkToggleStatus'])->name('admin.channels.bulk-toggle-status');
        Route::post('/channels/admin/scan-quality-all', [AdminChannelController::class, 'scanQualityAll'])->name('admin.channels.scan-quality-all');
        Route::get('/channels/admin/{channel}/generate-epg', [AdminChannelController::class, 'generateEpg'])->name('admin.channels.generate-epg');
        Route::get('/channels/admin/{channel}/playlist', [AdminChannelController::class, 'getPlaylist'])->name('admin.channels.playlist');
        Route::get('/channels/admin/{channel}/overlays', [AdminChannelController::class, 'getOverlays'])->name('admin.channels.overlays');
        Route::get('/channels/admin/{channel}/stats', [AdminChannelController::class, 'getStats'])->name('admin.channels.stats');
        Route::post('/channels/admin/{channel}/playlist', [AdminChannelController::class, 'addPlaylistItem'])->name('admin.channels.playlist.store');
        Route::put('/channels/admin/{channel}/playlist/{playlistItem}', [AdminChannelController::class, 'updatePlaylistItem'])->name('admin.channels.playlist.update');
        Route::delete('/channels/admin/{channel}/playlist/{playlistItem}', [AdminChannelController::class, 'removePlaylistItem'])->name('admin.channels.playlist.destroy');
        Route::post('/channels/admin/{channel}/playlist/reorder', [AdminChannelController::class, 'reorderPlaylistItems'])->name('admin.channels.playlist.reorder');
        Route::post('/channels/admin/{channel}/schedules', [AdminChannelController::class, 'addSchedule'])->name('admin.channels.schedules.store');
        Route::put('/channels/admin/{channel}/schedules/{schedule}', [AdminChannelController::class, 'updateSchedule'])->name('admin.channels.schedules.update');
        Route::delete('/channels/admin/{channel}/schedules/{schedule}', [AdminChannelController::class, 'removeSchedule'])->name('admin.channels.schedules.destroy');
        Route::post('/channels/admin/{channel}/overlays', [AdminChannelController::class, 'addOverlay'])->name('admin.channels.overlays.store');
        Route::put('/channels/admin/{channel}/overlays/{overlay}', [AdminChannelController::class, 'updateOverlay'])->name('admin.channels.overlays.update');
        Route::delete('/channels/admin/{channel}/overlays/{overlay}', [AdminChannelController::class, 'removeOverlay'])->name('admin.channels.overlays.destroy');
        Route::post('/channels/admin/{channel}/broadcast/start', [AdminChannelController::class, 'startBroadcast'])->name('admin.channels.broadcast.start');
        Route::post('/channels/admin/{channel}/broadcast/end', [AdminChannelController::class, 'endBroadcast'])->name('admin.channels.broadcast.end');
        Route::post('/channels/admin/{channel}/subscribe', [AdminChannelController::class, 'subscribeUser'])->name('admin.channels.subscribe');
        Route::delete('/channels/admin/{channel}/unsubscribe', [AdminChannelController::class, 'unsubscribeUser'])->name('admin.channels.unsubscribe');
        Route::get('/channels/admin/{channel}/analytics', [AdminChannelController::class, 'getAnalytics'])->name('admin.channels.analytics');
        Route::post('/channels/admin/{channel}/analytics/daily', [AdminChannelController::class, 'generateDailyAnalytics'])->name('admin.channels.analytics.daily');
        Route::post('/channels/admin/bulk-import', [AdminChannelController::class, 'bulkImport'])->name('admin.channels.bulk-import');
        Route::get('/channels/admin/bulk-export', [AdminChannelController::class, 'bulkExport'])->name('admin.channels.bulk-export');

        // ─── My Channel Image Upload ────────────────────────────────────────
        Route::post('/channels/admin/my-channel/upload-image', [AdminChannelController::class, 'uploadBrandingImage'])->name('channels.my-channel.upload-image');

        // ─── My Channel Content API ───────────────────────────────────────
        Route::get('/channels/admin/{channel}/my-channel/content', [AdminChannelController::class, 'myChannelContent'])->name('channels.my-channel.content');
        Route::post('/channels/admin/{channel}/my-channel/content/upload', [AdminChannelController::class, 'uploadContent'])->name('channels.my-channel.content.upload');
        Route::put('/channels/admin/{channel}/my-channel/content/{content}', [AdminChannelController::class, 'updateContent'])->name('channels.my-channel.content.update');
        Route::delete('/channels/admin/{channel}/my-channel/content/{content}', [AdminChannelController::class, 'destroyContent'])->name('channels.my-channel.content.destroy');

        // ─── My Channel Media Folders API ───────────────────────────────────
        Route::get('/channels/admin/{channel}/my-channel/folders', [AdminChannelController::class, 'myChannelFolders'])->name('channels.my-channel.folders');
        Route::post('/channels/admin/{channel}/my-channel/folders', [AdminChannelController::class, 'storeMyChannelFolder'])->name('channels.my-channel.folders.store');
        Route::put('/channels/admin/{channel}/my-channel/folders/{folder}', [AdminChannelController::class, 'updateMyChannelFolder'])->name('channels.my-channel.folders.update');
        Route::delete('/channels/admin/{channel}/my-channel/folders/{folder}', [AdminChannelController::class, 'destroyMyChannelFolder'])->name('channels.my-channel.folders.destroy');

        // ─── My Channel Playlist API ────────────────────────────────────────
        Route::get('/channels/admin/{channel}/my-channel/playlist', [AdminChannelController::class, 'getMyChannelPlaylist'])->name('channels.my-channel.playlist');
        Route::post('/channels/admin/{channel}/my-channel/playlist', [AdminChannelController::class, 'addToPlaylist'])->name('channels.my-channel.playlist.store');
        Route::put('/channels/admin/{channel}/my-channel/playlist/{playlistItem}', [AdminChannelController::class, 'updateMyChannelPlaylistItem'])->name('channels.my-channel.playlist.update');
        Route::delete('/channels/admin/{channel}/my-channel/playlist/{playlistItem}', [AdminChannelController::class, 'removeMyChannelPlaylistItem'])->name('channels.my-channel.playlist.destroy');
        Route::post('/channels/admin/{channel}/my-channel/playlist/reorder', [AdminChannelController::class, 'reorderMyChannelPlaylist'])->name('channels.my-channel.playlist.reorder');

        // ─── My Channel Settings API ────────────────────────────────────────
        Route::get('/channels/admin/{channel}/my-channel/settings', [AdminChannelController::class, 'getMyChannelSettings'])->name('channels.my-channel.settings');
        Route::put('/channels/admin/{channel}/my-channel/settings', [AdminChannelController::class, 'updateMyChannelSettings'])->name('channels.my-channel.settings.update');
        Route::put('/channels/admin/{channel}/my-channel/overlays-settings', [AdminChannelController::class, 'updateChannelOverlays'])->name('channels.my-channel.overlays-settings.update');

        // ─── My Channel Broadcast API ───────────────────────────────────────
        Route::get('/channels/admin/{channel}/my-channel/broadcast', [AdminChannelController::class, 'getBroadcastStatus'])->name('channels.my-channel.broadcast');
        Route::post('/channels/admin/{channel}/my-channel/broadcast/start', [AdminChannelController::class, 'startMyChannelBroadcast'])->name('channels.my-channel.broadcast.start');
        Route::post('/channels/admin/{channel}/my-channel/broadcast/stop', [AdminChannelController::class, 'stopMyChannelBroadcast'])->name('channels.my-channel.broadcast.stop');

        // ─── Channels ─────────────────────────────────────────────────────
        Route::get('/channels', [ChannelController::class, 'index'])->name('channels.index');
        Route::get('/channels/create', function () {
            return Inertia::render('Admin/Channels/Create', [
                'categories' => ContentCategory::where('is_active', true)->orderBy('sort_order')->get(),
                'bouquets' => \App\Models\Bouquet::where('is_active', true)->orderBy('sort_order')->get(),
                'epgSources' => \App\Models\EPGSource::where('is_active', true)->orderBy('name')->get(),
                'packages' => \App\Models\SubscriptionPackage::where('is_active', true)->orderBy('sort_order')->get(),
                'transcodingProfiles' => \App\Models\TranscodingProfile::where('is_active', true)->orderBy('name')->get(),
            ]);
        })->name('channels.create');
        Route::get('/channels/import', function () {
            return Inertia::render('Admin/Channels/BulkImport', [
                'categories' => ContentCategory::where('is_active', true)->orderBy('sort_order')->get(),
                'bouquets' => \App\Models\Bouquet::where('is_active', true)->orderBy('sort_order')->get(),
            ]);
        })->name('channels.import');
        Route::post('/channels/import', [ChannelController::class, 'bulkImport'])->name('channels.import.store');
        Route::get('/channels/multicast-scan', [\App\Http\Controllers\Admin\MulticastScanController::class, 'index'])->name('channels.multicast-scan');
        Route::post('/channels/multicast-scan/probe', [\App\Http\Controllers\Admin\MulticastScanController::class, 'scan'])->name('channels.multicast-scan.probe');
        Route::post('/channels/multicast-scan/import', [\App\Http\Controllers\Admin\MulticastScanController::class, 'import'])->name('channels.multicast-scan.import');
        Route::get('/channels/{channel}/edit', [ChannelController::class, 'show'])->name('channels.edit');
        Route::post('/channels', [ChannelController::class, 'store'])->name('channels.store');
        Route::put('/channels/{channel}', [ChannelController::class, 'update'])->name('channels.update');
        Route::delete('/channels/{channel}', [ChannelController::class, 'destroy'])->name('channels.destroy');
        Route::post('/channels/bulk-delete', [ChannelController::class, 'bulkDelete'])->name('channels.bulk-delete');
        Route::post('/channels/bulk-toggle-status', [ChannelController::class, 'bulkToggleStatus'])->name('channels.bulk-toggle-status');
        Route::post('/channels/{channel}/toggle-status', [ChannelController::class, 'toggleStatus'])->name('channels.toggle-status');
        Route::post('/channels/{channel}/test-stream', [ChannelController::class, 'testStream'])->name('channels.test-stream');
        Route::post('/channels/{channel}/verify-youtube', [ChannelController::class, 'verifyYouTube'])->name('channels.verify-youtube');
        Route::post('/channels/{channel}/check-source', [ChannelController::class, 'checkSource'])->name('channels.check-source');
        Route::post('/channels/{channel}/probe-sources', [ChannelController::class, 'probeSources'])->name('channels.probe-sources');
        Route::post('/channels/{channel}/refresh-source', [ChannelController::class, 'refreshSource'])->name('channels.refresh-source');
        Route::post('/channels/{channel}/stop-source', [ChannelController::class, 'stopSource'])->name('channels.stop-source');
        Route::post('/channels/{channel}/switch-source', [ChannelController::class, 'switchSource'])->name('channels.switch-source');
        Route::get('/channels/source-statuses', [ChannelController::class, 'sourceStatuses'])->name('channels.source-statuses');
        Route::post('/channels/upload-logo', [ChannelController::class, 'uploadLogo'])->name('channels.upload-logo');
        Route::post('/channels/{channel}/refresh-ingest', [\App\Http\Controllers\Admin\DashboardController::class, 'refreshIngest'])->name('channels.refresh-ingest');

        // ─── Categories ──────────────────────────────────────────────────
        Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::get('/categories/create', [CategoryController::class, 'create'])->name('categories.create');
        Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::get('/categories/{category}', [CategoryController::class, 'show'])->name('categories.show');
        Route::get('/categories/{category}/edit', [CategoryController::class, 'show'])->name('categories.edit');
        Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
        Route::post('/categories/{category}/toggle-status', [CategoryController::class, 'toggleStatus'])->name('categories.toggle-status');
        Route::get('/categories/{category}/channels', [CategoryController::class, 'channelAssignment'])->name('categories.channels');
        Route::post('/categories/{category}/channels', [CategoryController::class, 'assignChannels'])->name('categories.assign-channels');
        Route::post('/categories/{category}/channels/remove', [CategoryController::class, 'removeChannels'])->name('categories.remove-channels');
        Route::post('/categories/reorder', [CategoryController::class, 'reorder'])->name('categories.reorder');

        // ─── VOD ──────────────────────────────────────────────────────────
        Route::get('/vod', [VODController::class, 'index'])->name('vod.index');
        Route::get('/vod/create', function () {
            return Inertia::render('Admin/VOD/Create', [
                'categories' => ContentCategory::where('is_active', true)->get(),
                'bouquets' => \App\Models\Bouquet::where('is_active', true)->get(),
            ]);
        })->name('vod.create');
        Route::get('/vod/{vod}', [VODController::class, 'show'])->name('vod.show');
        Route::get('/vod/{vod}/edit', [VODController::class, 'show'])->name('vod.edit');
        Route::get('/vod/import', fn () => Inertia::render('Admin/VOD/Import', [
            'bouquets' => \App\Models\Bouquet::where('is_active', true)->get(),
        ]))->name('vod.import');
        Route::post('/vod', [VODController::class, 'store'])->name('vod.store');
        Route::post('/vod/import', [VODController::class, 'import'])->name('vod.import.store');
        Route::post('/vod/upload', [VODController::class, 'upload'])->name('vod.upload');
        Route::post('/vod/import/url', [VODController::class, 'importFromUrl'])->name('vod.import.url');
        Route::post('/vod/import/xtream', [VODController::class, 'importFromXtream'])->name('vod.import.xtream');
        Route::put('/vod/{vod}', [VODController::class, 'update'])->name('vod.update');
        Route::delete('/vod/{vod}', [VODController::class, 'destroy'])->name('vod.destroy');
        Route::post('/vod/{vod}/toggle-featured', [VODController::class, 'toggleFeatured'])->name('vod.toggle-featured');

        // ─── VOD Episode / Season management ─────────────────────────────
        Route::get('/vod/{vod}/episodes', [VODController::class, 'getEpisodes'])->name('vod.episodes.index');
        Route::post('/vod/{vod}/episodes', [VODController::class, 'storeEpisode'])->name('vod.episodes.store');
        Route::post('/vod/{vod}/episodes/upload', [VODController::class, 'uploadEpisodeFile'])->name('vod.episodes.upload');
        Route::put('/vod/{vod}/episodes/{media}', [VODController::class, 'updateEpisode'])->name('vod.episodes.update');
        Route::delete('/vod/{vod}/episodes/{media}', [VODController::class, 'destroyEpisode'])->name('vod.episodes.destroy');

        // ─── TMDB Integration Routes ─────────────────────────────────────
        Route::post('/vod/search-tmdb', [\App\Http\Controllers\Admin\VODController::class, 'searchTMDB'])->name('vod.search-tmdb');
        Route::post('/vod/tmdb-details', [\App\Http\Controllers\Admin\VODController::class, 'tmdbDetails'])->name('vod.tmdb-details');
        Route::post('/vod/import-tmdb', [\App\Http\Controllers\Admin\VODController::class, 'importFromTMDB'])->name('vod.import-tmdb');
        Route::post('/vod/{vod}/auto-tmdb', [\App\Http\Controllers\Admin\VODController::class, 'autoPopulateTMDB'])->name('vod.auto-tmdb');
        Route::post('/vod/{vod}/tmdb-episodes', [\App\Http\Controllers\Admin\VODController::class, 'tmdbEpisodes'])->name('vod.tmdb-episodes');
        Route::get('/vod/trending', [\App\Http\Controllers\Admin\VODController::class, 'trending'])->name('vod.trending');
        Route::get('/vod/popular', [\App\Http\Controllers\Admin\VODController::class, 'popular'])->name('vod.popular');

        // ─── Bouquets ─────────────────────────────────────────────────────
        Route::resource('bouquets', BouquetController::class)->except(['edit']);
        Route::post('/bouquets/{bouquet}/toggle-status', [BouquetController::class, 'toggleStatus'])->name('bouquets.toggle-status');
        Route::post('/bouquets/{bouquet}/channels', [BouquetController::class, 'addChannels'])->name('bouquets.channels.add');
        Route::delete('/bouquets/{bouquet}/channels/{channel}', [BouquetController::class, 'removeChannel'])->name('bouquets.channels.remove');
        Route::put('/bouquets/{bouquet}/channels/reorder', [BouquetController::class, 'updateChannelOrder'])->name('bouquets.channels.reorder');
        Route::delete('/bouquets/{bouquet}/channels/all', [BouquetController::class, 'deleteAllChannels'])->name('bouquets.channels.deleteAll');
        Route::post('/bouquets/{bouquet}/clone', [BouquetController::class, 'cloneBouquet'])->name('bouquets.clone');
        Route::get('/bouquets/{bouquet}/export', [BouquetController::class, 'export'])->name('bouquets.export');
        Route::post('/bouquets/{bouquet}/import', [BouquetController::class, 'import'])->name('bouquets.import');

        // ─── EPG ──────────────────────────────────────────────────────────
        Route::get('/epg', [EpgController::class, 'index'])->name('epg.index');
        Route::get('/epg/create', [EpgController::class, 'create'])->name('epg.create');
        Route::post('/epg', [EpgController::class, 'store'])->name('epg.store');
        Route::get('/epg/{epgSource}', [EpgController::class, 'show'])->name('epg.show');
        Route::get('/epg/{epgSource}/edit', [EpgController::class, 'show'])->name('epg.edit');
        Route::put('/epg/{epgSource}', [EpgController::class, 'update'])->name('epg.update');
        Route::delete('/epg/{epgSource}', [EpgController::class, 'destroy'])->name('epg.destroy');
        Route::post('/epg/{epgSource}/update-now', [EpgController::class, 'updateNow'])->name('epg.update-now');
        Route::post('/epg/{epgSource}/preview', [EpgController::class, 'preview'])->name('epg.preview');
        Route::get('/epg/programs/list', [EpgController::class, 'programs'])->name('epg.programs');
        Route::post('/epg/programs', [EpgController::class, 'storeProgram'])->name('epg.programs.store');
        Route::put('/epg/programs/{program}', [EpgController::class, 'updateProgram'])->name('epg.programs.update');
        Route::delete('/epg/programs/{program}', [EpgController::class, 'destroyProgram'])->name('epg.programs.destroy');
        Route::post('/epg/update-all', [EpgController::class, 'updateNow'])->name('epg.update-all');
        Route::post('/epg/clear-expired', [EpgController::class, 'clearExpired'])->name('epg.clear-expired');
        Route::get('/epg/programs/export', [EpgController::class, 'exportPrograms'])->name('epg.programs.export');
        Route::post('/epg/update-trigger', [EpgController::class, 'triggerUpdate'])->name('epg.update.trigger');

        // ─── Transcoding ───────────────────────────────────────────────
        Route::get('/transcoding', [TranscodingController::class, 'index'])->name('transcoding.index');
        Route::get('/transcoding/create', [TranscodingController::class, 'create'])->name('transcoding.create');
        Route::post('/transcoding', [TranscodingController::class, 'store'])->name('transcoding.store');
        Route::get('/transcoding/jobs', [TranscodingController::class, 'jobs'])->name('transcoding.jobs');
        Route::get('/transcoding/jobs/create', [TranscodingController::class, 'createJob'])->name('transcoding.jobs.create');
        Route::post('/transcoding/jobs', [TranscodingController::class, 'storeJob'])->name('transcoding.jobs.store');
        Route::post('/transcoding/jobs/clear-completed', [TranscodingController::class, 'clearCompleted'])->name('transcoding.jobs.clear');
        Route::post('/transcoding/jobs/{job}/pause', [TranscodingController::class, 'pauseJob'])->name('transcoding.jobs.pause');
        Route::post('/transcoding/jobs/{job}/resume', [TranscodingController::class, 'resumeJob'])->name('transcoding.jobs.resume');
        Route::post('/transcoding/jobs/{job}/cancel', [TranscodingController::class, 'cancelJob'])->name('transcoding.jobs.cancel');
        Route::get('/transcoding/{profile}', [TranscodingController::class, 'show'])->name('transcoding.show');
        Route::get('/transcoding/{profile}/edit', [TranscodingController::class, 'show'])->name('transcoding.edit');
        Route::put('/transcoding/{profile}', [TranscodingController::class, 'update'])->name('transcoding.update');
        Route::delete('/transcoding/{profile}', [TranscodingController::class, 'destroy'])->name('transcoding.destroy');

        // ─── Subscriptions ────────────────────────────────────────────────
        Route::get('/subscriptions/packages', [PackageController::class, 'index'])->name('subscriptions.packages');
        Route::get('/subscriptions/manage', function () {
            $subscriptions = \App\Models\Subscription::with(['user', 'subscriptionPackage'])->latest()->paginate(20);
            return Inertia::render('Admin/Subscriptions/Manage', [
                'subscriptions' => $subscriptions,
                'packages' => SubscriptionPackage::where('is_active', true)->get(),
                'stats' => [
                    'total_active' => \App\Models\Subscription::where('status', 'active')->count(),
                    'monthly_revenue' => \App\Models\Subscription::where('status', 'active')->sum('amount_paid') ?? 0,
                    'expiring_soon' => \App\Models\Subscription::where('status', 'active')->where('end_date', '<=', now()->addDays(7))->count(),
                    'expired' => \App\Models\Subscription::where('status', 'expired')->count(),
                ],
            ]);
        })->name('subscriptions.manage');
        Route::post('/subscriptions/packages', [PackageController::class, 'store'])->name('subscriptions.packages.store');
        Route::put('/subscriptions/packages/{package}', [PackageController::class, 'update'])->name('subscriptions.packages.update');
        Route::delete('/subscriptions/packages/{package}', [PackageController::class, 'destroy'])->name('subscriptions.packages.destroy');
        Route::post('/subscriptions/{subscription}/extend', function (\Illuminate\Http\Request $request, \App\Models\Subscription $subscription) {
            $validated = $request->validate([
                'days' => 'required|integer|min:1|max:365',
            ]);
            $subscription->end_date = $subscription->end_date->addDays($validated['days']);
            $subscription->save();
            return back()->with('success', 'Subscription extended by ' . $validated['days'] . ' days.');
        })->name('subscriptions.extend');
        Route::post('/subscriptions/{subscription}/cancel', function (\Illuminate\Http\Request $request, \App\Models\Subscription $subscription) {
            $subscription->status = 'cancelled';
            $subscription->cancelled_at = now();
            $subscription->save();
            return back()->with('success', 'Subscription cancelled.');
        })->name('subscriptions.cancel');
        Route::put('/subscriptions/{subscription}', function (\Illuminate\Http\Request $request, \App\Models\Subscription $subscription) {
            $subscription->update($request->validate([
                'subscription_package_id' => 'nullable|exists:subscription_packages,id',
                'end_date' => 'nullable|date',
                'status' => 'nullable|in:active,suspended,expired',
            ]));
            return back()->with('success', 'Subscription updated.');
        })->name('subscriptions.update');

        // ─── Servers ──────────────────────────────────────────────────────
        Route::get('/servers', [ServerController::class, 'index'])->name('servers.index');
        Route::get('/servers/monitor', function () {
            $server = \App\Models\Server::first();
            $stats = $server ? [
                'cpu_usage' => $server->cpu_usage ?? 0,
                'memory_usage' => $server->memory_usage ?? 0,
                'disk_usage' => $server->disk_usage ?? 0,
                'network_in' => $server->network_in ?? 0,
                'network_out' => $server->network_out ?? 0,
                'active_streams' => $server->active_streams ?? 0,
                'connected_users' => $server->connected_users ?? 0,
                'uptime' => $server->uptime ?? '0d',
                'events' => \App\Models\SystemLog::latest()->take(10)->get() ?? [],
            ] : [];
            return Inertia::render('Admin/Servers/Monitor', [
                'server' => $server,
                'stats' => $stats,
            ]);
        })->name('servers.monitor');
        Route::post('/servers', [ServerController::class, 'store'])->name('servers.store');
        Route::put('/servers/{server}', [ServerController::class, 'update'])->name('servers.update');
        Route::delete('/servers/{server}', fn (\App\Models\Server $server) => $server->delete())->name('servers.destroy');
        Route::post('/servers/{server}/disconnect', fn () => back())->name('servers.disconnect');
        Route::post('/servers/{server}/test', [ServerController::class, 'test'])->name('servers.test');

        // ─── Settings ─────────────────────────────────────────────────────
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
        Route::get('/settings/general', [SettingsController::class, 'general'])->name('settings.general');
        Route::put('/settings/general', [SettingsController::class, 'updateGeneral'])->name('settings.general.update');
        Route::get('/settings/localization', [SettingsController::class, 'localization'])->name('settings.localization');
        Route::put('/settings/localization', [SettingsController::class, 'updateLocalization'])->name('settings.localization.update');
        Route::get('/settings/channels', [SettingsController::class, 'channels'])->name('settings.channels');
        Route::put('/settings/channels', [SettingsController::class, 'updateChannels'])->name('settings.channels.update');
        Route::get('/settings/vod', [SettingsController::class, 'vod'])->name('settings.vod');
        Route::put('/settings/vod', [SettingsController::class, 'updateVod'])->name('settings.vod.update');
        Route::get('/settings/epg', [SettingsController::class, 'epg'])->name('settings.epg');
        Route::put('/settings/epg', [SettingsController::class, 'updateEpg'])->name('settings.epg.update');
        Route::get('/settings/users', [SettingsController::class, 'users'])->name('settings.users');
        Route::put('/settings/users', [SettingsController::class, 'updateUsers'])->name('settings.users.update');
        Route::get('/settings/security', [SettingsController::class, 'security'])->name('settings.security');
        Route::put('/settings/security', [SettingsController::class, 'updateSecurity'])->name('settings.security.update');
        Route::get('/settings/roles', [SettingsController::class, 'roles'])->name('settings.roles');
        Route::put('/settings/roles', [SettingsController::class, 'updateRoles'])->name('settings.roles.update');
        Route::get('/settings/payments', [SettingsController::class, 'payments'])->name('settings.payments');
        Route::put('/settings/payments', [SettingsController::class, 'updatePayments'])->name('settings.payments.update');
        Route::get('/settings/billing', [SettingsController::class, 'billing'])->name('settings.billing');
        Route::put('/settings/billing', [SettingsController::class, 'updateBilling'])->name('settings.billing.update');
        Route::get('/settings/server', [SettingsController::class, 'server'])->name('settings.server');
        Route::put('/settings/server', [SettingsController::class, 'updateServer'])->name('settings.server.update');
        Route::get('/settings/cache', [SettingsController::class, 'cache'])->name('settings.cache');
        Route::put('/settings/cache', [SettingsController::class, 'updateCache'])->name('settings.cache.update');
        Route::get('/settings/performance', [SettingsController::class, 'performance'])->name('settings.performance');
        Route::put('/settings/performance', [SettingsController::class, 'updatePerformance'])->name('settings.performance.update');
        Route::get('/settings/email', [SettingsController::class, 'email'])->name('settings.email');
        Route::put('/settings/email', [SettingsController::class, 'updateEmail'])->name('settings.email.update');
        Route::get('/settings/notifications', [SettingsController::class, 'notifications'])->name('settings.notifications');
        Route::put('/settings/notifications', [SettingsController::class, 'updateNotifications'])->name('settings.notifications.update');
        Route::get('/settings/logging', [SettingsController::class, 'logging'])->name('settings.logging');
        Route::put('/settings/logging', [SettingsController::class, 'updateLogging'])->name('settings.logging.update');
        Route::get('/settings/monitoring', [SettingsController::class, 'monitoring'])->name('settings.monitoring');
        Route::put('/settings/monitoring', [SettingsController::class, 'updateMonitoring'])->name('settings.monitoring.update');
        Route::get('/settings/backup', [SettingsController::class, 'backup'])->name('settings.backup');
        Route::put('/settings/backup', [SettingsController::class, 'updateBackup'])->name('settings.backup.update');
        Route::get('/settings/api', [SettingsController::class, 'api'])->name('settings.api');
        Route::put('/settings/api', [SettingsController::class, 'updateApi'])->name('settings.api.update');
        Route::get('/settings/integrations', [SettingsController::class, 'integrations'])->name('settings.integrations');
        Route::put('/settings/integrations', [SettingsController::class, 'updateIntegrations'])->name('settings.integrations.update');
        Route::get('/settings/variables', [SettingsController::class, 'variables'])->name('settings.variables');
        Route::put('/settings/variables', [SettingsController::class, 'updateVariables'])->name('settings.variables.update');
        Route::get('/settings/cronjobs', [SettingsController::class, 'cronjobs'])->name('settings.cronjobs');
        Route::put('/settings/cronjobs', [SettingsController::class, 'updateCronjobs'])->name('settings.cronjobs.update');
        Route::get('/settings/domains', [SettingsController::class, 'domains'])->name('settings.domains');
        Route::put('/settings/domains', [SettingsController::class, 'updateDomains'])->name('settings.domains.update');

        // ─── License Management ────────────────────────────────────────────
        Route::get('/settings/license', [SettingsController::class, 'license'])->name('settings.license');
        Route::post('/settings/license/activate', [SettingsController::class, 'activateLicense'])->name('settings.license.activate');
        Route::post('/settings/license/deactivate', [SettingsController::class, 'deactivateLicense'])->name('settings.license.deactivate');
        Route::delete('/settings/license/devices/{device}', [SettingsController::class, 'revokeDevice'])->name('settings.license.devices.revoke');

        // ─── Settings Actions ──────────────────────────────────────────────
        Route::post('/settings/api/regenerate', [SettingsController::class, 'regenerateApiKeys'])->name('settings.api.regenerate');
        Route::post('/settings/backup/run', [SettingsController::class, 'runBackup'])->name('settings.backup.run');
        Route::post('/settings/backup/restore', [SettingsController::class, 'restoreBackup'])->name('settings.backup.restore');
        Route::post('/settings/cache/clear', [SettingsController::class, 'clearCache'])->name('settings.cache.clear');
        Route::post('/settings/cronjobs/run', [SettingsController::class, 'runCronjob'])->name('settings.cronjobs.run');
        Route::post('/settings/email/test', [SettingsController::class, 'testEmail'])->name('settings.email.test');
        Route::get('/settings/logging/view', [SettingsController::class, 'viewLogs'])->name('settings.logging.view');
        Route::post('/settings/logging/clear', [SettingsController::class, 'clearLogs'])->name('settings.logging.clear');

        // ─── Resellers ─────────────────────────────────────────────────────
        Route::get('/resellers', [\App\Http\Controllers\Admin\ResellerController::class, 'index'])->name('resellers.index');
        Route::get('/resellers/create', [\App\Http\Controllers\Admin\ResellerController::class, 'create'])->name('resellers.create');
        Route::post('/resellers', [\App\Http\Controllers\Admin\ResellerController::class, 'store'])->name('resellers.store');
        Route::get('/resellers/{reseller}', [\App\Http\Controllers\Admin\ResellerController::class, 'show'])->name('resellers.show');
        Route::get('/resellers/{reseller}/edit', [\App\Http\Controllers\Admin\ResellerController::class, 'edit'])->name('resellers.edit');
        Route::put('/resellers/{reseller}', [\App\Http\Controllers\Admin\ResellerController::class, 'update'])->name('resellers.update');
        Route::delete('/resellers/{reseller}', [\App\Http\Controllers\Admin\ResellerController::class, 'destroy'])->name('resellers.destroy');
        Route::post('/resellers/{reseller}/toggle-status', [\App\Http\Controllers\Admin\ResellerController::class, 'toggle'])->name('resellers.toggle-status');
        Route::post('/resellers/{reseller}/assign-subscription', [\App\Http\Controllers\Admin\ResellerController::class, 'assignSubscription'])->name('resellers.assignSubscription');

        // ─── Invoices ──────────────────────────────────────────────────────
        Route::get('/invoices', [\App\Http\Controllers\Admin\InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/{invoice}', [\App\Http\Controllers\Admin\InvoiceController::class, 'show'])->name('invoices.show');
        Route::put('/invoices/{invoice}', [\App\Http\Controllers\Admin\InvoiceController::class, 'update'])->name('invoices.update');
        Route::delete('/invoices/{invoice}', [\App\Http\Controllers\Admin\InvoiceController::class, 'destroy'])->name('invoices.destroy');
        Route::get('/invoices/export/csv', [\App\Http\Controllers\Admin\InvoiceController::class, 'export'])->name('invoices.export');

        // ─── Reports & Analytics ────────────────────────────────────────────
        Route::get('/reports', [\App\Http\Controllers\Admin\AnalyticsController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [\App\Http\Controllers\Admin\AnalyticsController::class, 'export'])->name('reports.export');
        Route::get('/reports/logs', [\App\Http\Controllers\Admin\LogController::class, 'index'])->name('reports.logs');

        // ─── Quality Detection ─────────────────────────────────────────
        Route::get('/settings/quality-detection', [QualityDetectionController::class, 'index'])->name('settings.quality-detection');
        Route::put('/settings/quality-detection', [QualityDetectionController::class, 'updateSettings'])->name('settings.quality-detection.update');
        Route::post('/settings/quality-detection/scan/{id}', [QualityDetectionController::class, 'scanChannel'])->name('quality.scan.channel');
        Route::post('/settings/quality-detection/scan-vod/{id}', [QualityDetectionController::class, 'scanVOD'])->name('quality.scan.vod');
        Route::post('/settings/quality-detection/scan-all-channels', [QualityDetectionController::class, 'scanAllChannels'])->name('quality.scan.all.channels');
        Route::post('/settings/quality-detection/scan-all-vod', [QualityDetectionController::class, 'scanAllVOD'])->name('quality.scan.all.vod');
        Route::get('/settings/quality-detection/stats', [QualityDetectionController::class, 'getStats'])->name('quality.stats');

        // ─── Notifications ────────────────────────────────────────────────
        Route::get('/notifications/send', function () {
            return Inertia::render('Admin/Notifications/Send', [
                'users' => \App\Models\User::select('id', 'first_name', 'last_name', 'email')->get(),
                'channels' => Channel::select('id', 'name')->get(),
                'stats' => [
                    'sent_today' => Notification::where('created_at', '>=', now()->startOfDay())->count(),
                    'delivered' => Notification::whereNotNull('read_at')->count(),
                    'total_recipients' => \App\Models\User::count(),
                ],
            ]);
        })->name('notifications.send');
        Route::post('/notifications/send', [NotificationController::class, 'send'])->name('notifications.send.post');
    });

// ─── Xtream Codes API (Public - for IPTV player clients) ──────────────────────
Route::get('/player_api.php', function (Request $request) {
    $xtream = new \App\Http\Controllers\XtreamController();
    $action = $request->input('action', '');

    return match ($action) {
        'auth'                 => $xtream->auth($request),
        'get_live_streams'     => $xtream->liveStreams($request),
        'get_vod_streams'      => $xtream->vodStreams($request),
        'get_series'           => $xtream->series($request),
        'get_live_categories'  => $xtream->liveCategories($request),
        'get_vod_categories'   => $xtream->vodCategories($request),
        'get_series_categories'=> $xtream->seriesCategories($request),
        'get_epg_streams'      => $xtream->epg($request),
        'get_vod_info'         => $xtream->vodInfo($request),
        'get_series_info'      => $xtream->seriesInfo($request),
        default                => $xtream->auth($request),
    };
});

// ─── Client Channel/Playout System ──────────────────────────────────
Route::middleware(['auth:web', 'license.check'])->prefix('client')->name('client.')->group(function () {
    Route::get('/channels', [\App\Http\Controllers\Client\ChannelController::class, 'index'])->name('channels.index');
    Route::get('/channels/create', fn () => \Inertia\Inertia::render('Client/Channel/Create'))->name('channels.create');
    Route::get('/channels/{channel}', [\App\Http\Controllers\Client\ChannelController::class, 'show'])->name('channels.show');
    Route::post('/channels', [\App\Http\Controllers\Client\ChannelController::class, 'store'])->name('channels.store');
    Route::put('/channels/{channel}', [\App\Http\Controllers\Client\ChannelController::class, 'update'])->name('channels.update');
    Route::delete('/channels/{channel}', [\App\Http\Controllers\Client\ChannelController::class, 'destroy'])->name('channels.destroy');
    Route::post('/channels/{channel}/toggle-status', [\App\Http\Controllers\Client\ChannelController::class, 'toggleStatus'])->name('channels.toggle-status');
    Route::get('/channels/my', [\App\Http\Controllers\Client\ChannelController::class, 'myChannels'])->name('channels.my');
    Route::get('/channels/{channel}/edit', [\App\Http\Controllers\Client\ChannelController::class, 'edit'])->name('channels.edit');
    Route::post('/channels/{channel}/subscribe', [\App\Http\Controllers\Client\ChannelController::class, 'subscribe'])->name('channels.subscribe');
    Route::delete('/channels/{channel}/unsubscribe', [\App\Http\Controllers\Client\ChannelController::class, 'unsubscribe'])->name('channels.unsubscribe');
    Route::get('/channels/{channel}/subscriptions', [\App\Http\Controllers\Client\ChannelController::class, 'subscriptions'])->name('channels.subscriptions');
    Route::get('/channels/{channel}/comments', [\App\Http\Controllers\Client\ChannelController::class, 'comments'])->name('channels.comments');
    Route::post('/channels/{channel}/comments', [\App\Http\Controllers\Client\ChannelController::class, 'addComment'])->name('channels.comments.store');
    Route::post('/channels/{channel}/views', [\App\Http\Controllers\Client\ChannelController::class, 'recordView'])->name('channels.views');
    Route::get('/channels/{channel}/stats', [\App\Http\Controllers\Client\ChannelController::class, 'stats'])->name('channels.stats');
    Route::get('/channels/{channel}/playlist', [\App\Http\Controllers\Client\ChannelController::class, 'playlistItems'])->name('channels.playlist');
    Route::post('/channels/{channel}/playlist', [\App\Http\Controllers\Client\ChannelController::class, 'addPlaylistItem'])->name('channels.playlist.store');
    Route::put('/channels/{channel}/playlist/{playlistItem}', [\App\Http\Controllers\Client\ChannelController::class, 'updatePlaylistItem'])->name('channels.playlist.update');
    Route::delete('/channels/{channel}/playlist/{playlistItem}', [\App\Http\Controllers\Client\ChannelController::class, 'removePlaylistItem'])->name('channels.playlist.destroy');
    Route::post('/channels/{channel}/playlist/reorder', [\App\Http\Controllers\Client\ChannelController::class, 'reorderPlaylistItems'])->name('channels.playlist.reorder');
    Route::get('/channels/{channel}/schedules', [\App\Http\Controllers\Client\ChannelController::class, 'schedules'])->name('channels.schedules');
    Route::post('/channels/{channel}/schedules', [\App\Http\Controllers\Client\ChannelController::class, 'addSchedule'])->name('channels.schedules.store');
    Route::put('/channels/{channel}/schedules/{schedule}', [\App\Http\Controllers\Client\ChannelController::class, 'updateSchedule'])->name('channels.schedules.update');
    Route::delete('/channels/{channel}/schedules/{schedule}', [\App\Http\Controllers\Client\ChannelController::class, 'removeSchedule'])->name('channels.schedules.destroy');
    Route::get('/channels/{channel}/overlays', [\App\Http\Controllers\Client\ChannelController::class, 'overlays'])->name('channels.overlays');
    Route::post('/channels/{channel}/overlays', [\App\Http\Controllers\Client\ChannelController::class, 'addOverlay'])->name('channels.overlays.store');
    Route::put('/channels/{channel}/overlays/{overlay}', [\App\Http\Controllers\Client\ChannelController::class, 'updateOverlay'])->name('channels.overlays.update');
    Route::delete('/channels/{channel}/overlays/{overlay}', [\App\Http\Controllers\Client\ChannelController::class, 'removeOverlay'])->name('channels.overlays.destroy');
    Route::post('/channels/{channel}/broadcast/start', [\App\Http\Controllers\Client\ChannelController::class, 'startBroadcast'])->name('channels.broadcast.start');
    Route::post('/channels/{channel}/broadcast/end', [\App\Http\Controllers\Client\ChannelController::class, 'endBroadcast'])->name('channels.broadcast.end');
});

// ─── Client VOD System ───────────────────────────────────────────────
Route::middleware(['auth:web', 'license.check'])->prefix('vod')->name('vod.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Client\VODController::class, 'index'])->name('index');
    Route::get('/movie/{id}', [\App\Http\Controllers\Client\VODController::class, 'showMovie'])->name('movie');
    Route::get('/series/{id}', [\App\Http\Controllers\Client\VODController::class, 'showSeries'])->name('series');
    Route::get('/play/{id}', [\App\Http\Controllers\Client\VODController::class, 'play'])->name('play');
    Route::get('/episode/{id}', [\App\Http\Controllers\Client\VODController::class, 'playEpisode'])->name('episode');
    Route::post('/progress', [\App\Http\Controllers\Client\VODController::class, 'progress'])->name('progress');
    Route::post('/toggle-favorite', [\App\Http\Controllers\Client\VODController::class, 'toggleFavorite'])->name('toggle-favorite');
    Route::post('/toggle-watchlist', [\App\Http\Controllers\Client\VODController::class, 'toggleWatchlist'])->name('toggle-watchlist');
    Route::post('/review', [\App\Http\Controllers\Client\VODController::class, 'review'])->name('review');
    Route::get('/history', [\App\Http\Controllers\Client\VODController::class, 'history'])->name('history');
    Route::get('/favorites', [\App\Http\Controllers\Client\VODController::class, 'favorites'])->name('favorites');
    Route::get('/watchlist', [\App\Http\Controllers\Client\VODController::class, 'watchlist'])->name('watchlist');
    Route::get('/search', [\App\Http\Controllers\Client\VODController::class, 'search'])->name('search');
    Route::get('/stream/{id}', [\App\Http\Controllers\Client\VODController::class, 'stream'])->name('stream');
});

Route::get('/get.php', [\App\Http\Controllers\XtreamController::class, 'm3u']);
Route::get('/playlist/{token}/m3u', [\App\Http\Controllers\PlaylistController::class, 'generate'])->name('playlist.m3u');
Route::get('/live/{username}/{password}/{streamId}', [\App\Http\Controllers\XtreamController::class, 'streamLive'])->where('streamId', '.*');
Route::get('/movie/{username}/{password}/{streamId}', [\App\Http\Controllers\XtreamController::class, 'streamVod'])->where('streamId', '.*');
Route::get('/series/{username}/{password}/{streamId}', [\App\Http\Controllers\XtreamController::class, 'streamSeries'])->where('streamId', '.*');

// ─── Catch-all ─────────────────────────────────────────────────────────────────
Route::get('/{any}', fn () => redirect()->route('login'))->where('any', '.*');

        Route::get("/channels/admin/{channel}/sweep", [AdminChannelController::class, "scanMulticast"])->name("admin.channels.scan-multicast");
