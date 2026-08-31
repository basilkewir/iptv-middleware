<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
| These routes are merged into web.php and protected by auth + admin middleware.
| Kept separate for organization. Referenced in Kernel.php or loaded from web.php.
*/

Route::middleware(['auth', \App\Http\Middleware\AdminMiddleware::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        // Dashboard
        Route::get('/', fn () => Inertia::render('Admin/Dashboard'))->name('dashboard');

        // ─── Users ────────────────────────────────────────────────────────
        Route::resource('users', App\Http\Controllers\Admin\UserController::class)->except(['create', 'edit']);

        // ─── Clients ──────────────────────────────────────────────────────
        Route::get('/clients', [App\Http\Controllers\Admin\ClientController::class, 'index'])->name('clients.index');
        Route::get('/clients/create', [App\Http\Controllers\Admin\ClientController::class, 'create'])->name('clients.create');
        Route::post('/clients', [App\Http\Controllers\Admin\ClientController::class, 'store'])->name('clients.store');
        Route::get('/clients/{client}', [App\Http\Controllers\Admin\ClientController::class, 'show'])->name('clients.show');
        Route::get('/clients/{client}/edit', [App\Http\Controllers\Admin\ClientController::class, 'edit'])->name('clients.edit');
        Route::put('/clients/{client}', [App\Http\Controllers\Admin\ClientController::class, 'update'])->name('clients.update');
        Route::delete('/clients/{client}', [App\Http\Controllers\Admin\ClientController::class, 'destroy'])->name('clients.destroy');
        Route::post('/clients/{client}/toggle-status', [App\Http\Controllers\Admin\ClientController::class, 'toggleStatus'])->name('clients.toggleStatus');
        Route::post('/clients/{client}/change-package', [App\Http\Controllers\Admin\ClientController::class, 'changePackage'])->name('clients.changePackage');
        Route::post('/clients/{client}/extend-expiry', [App\Http\Controllers\Admin\ClientController::class, 'extendExpiry'])->name('clients.extendExpiry');
        Route::post('/clients/{client}/reset-password', [App\Http\Controllers\Admin\ClientController::class, 'resetPassword'])->name('clients.resetPassword');
        Route::post('/clients/{client}/generate-m3u', [App\Http\Controllers\Admin\ClientController::class, 'generateM3u'])->name('clients.generateM3u');
        Route::post('/clients/{client}/send-credentials', [App\Http\Controllers\Admin\ClientController::class, 'sendCredentials'])->name('clients.sendCredentials');
        Route::get('/clients/{client}/report', [App\Http\Controllers\Admin\ClientController::class, 'report'])->name('clients.report');
        Route::get('/clients/bulk-import', [App\Http\Controllers\Admin\ClientController::class, 'bulkImportForm'])->name('clients.bulkImportForm');
        Route::post('/clients/bulk-import', [App\Http\Controllers\Admin\ClientController::class, 'bulkImport'])->name('clients.bulkImport');
        Route::get('/clients/bulk-template', [App\Http\Controllers\Admin\ClientController::class, 'bulkTemplate'])->name('clients.bulkTemplate');
        Route::post('/clients/bulk-action', [App\Http\Controllers\Admin\ClientController::class, 'bulkAction'])->name('clients.bulkAction');

        // ─── Channels ─────────────────────────────────────────────────────
        // ─── Multicast Scanner (must be before resource to avoid route collision) ───
        Route::get('/channels/multicast-scan', [App\Http\Controllers\Admin\MulticastScanController::class, 'index'])->name('channels.multicast-scan');
        Route::post('/channels/multicast-scan/probe', [App\Http\Controllers\Admin\MulticastScanController::class, 'scan'])->name('channels.multicast-scan.probe');
        Route::post('/channels/multicast-scan/import', [App\Http\Controllers\Admin\MulticastScanController::class, 'import'])->name('channels.multicast-scan.import');

        Route::resource('channels', App\Http\Controllers\Admin\ChannelController::class)->except(['create', 'edit']);
        Route::post('/channels/{channel}/toggle-status', [App\Http\Controllers\Admin\ChannelController::class, 'toggleStatus'])->name('channels.toggleStatus');
        Route::post('/channels/bulk-import', [App\Http\Controllers\Admin\ChannelController::class, 'bulkImport'])->name('channels.bulkImport');

        // ─── VOD ──────────────────────────────────────────────────────────
        Route::resource('vod', App\Http\Controllers\Admin\VodController::class)->except(['create', 'edit']);
        Route::post('/vod/bulk-import', [App\Http\Controllers\Admin\VodController::class, 'bulkImport'])->name('vod.bulkImport');

        // ─── EPG ──────────────────────────────────────────────────────────
        Route::resource('epg', App\Http\Controllers\Admin\EpgController::class)->except(['create', 'edit']);
        Route::post('/epg/import', [App\Http\Controllers\Admin\EpgController::class, 'import'])->name('epg.import');
        Route::post('/epg/refresh', [App\Http\Controllers\Admin\EpgController::class, 'refresh'])->name('epg.refresh');

        // ─── Subscriptions ────────────────────────────────────────────────
        Route::resource('subscriptions', App\Http\Controllers\Admin\SubscriptionController::class)->only(['index', 'show', 'destroy']);
        Route::get('/subscriptions/plans', [App\Http\Controllers\Admin\SubscriptionController::class, 'plans'])->name('subscriptions.plans');
        Route::resource('subscription-plans', App\Http\Controllers\Admin\SubscriptionPlanController::class)->except(['create', 'edit']);
        Route::post('/subscriptions/{subscription}/assign', [App\Http\Controllers\Admin\SubscriptionController::class, 'assign'])->name('subscriptions.assign');

        // ─── Servers ──────────────────────────────────────────────────────
        Route::resource('servers', App\Http\Controllers\Admin\ServerController::class)->except(['create', 'edit']);
        Route::post('/servers/{server}/toggle', [App\Http\Controllers\Admin\ServerController::class, 'toggle'])->name('servers.toggle');
        Route::get('/servers/{server}/load', [App\Http\Controllers\Admin\ServerController::class, 'load'])->name('servers.load');

        // ─── Resellers ────────────────────────────────────────────────────
        Route::resource('resellers', App\Http\Controllers\Admin\ResellerController::class);
        Route::post('/resellers/{reseller}/toggle-status', [App\Http\Controllers\Admin\ResellerController::class, 'toggle'])->name('resellers.toggle-status');
        Route::post('/resellers/{reseller}/assign-subscription', [App\Http\Controllers\Admin\ResellerController::class, 'assignSubscription'])->name('resellers.assignSubscription');

        // ─── Bouquets ─────────────────────────────────────────────────────
        Route::resource('bouquets', App\Http\Controllers\Admin\BouquetController::class)->except(['edit']);
        Route::post('/bouquets/{bouquet}/toggle-status', [App\Http\Controllers\Admin\BouquetController::class, 'toggleStatus'])->name('bouquets.toggleStatus');
        Route::post('/bouquets/{bouquet}/channels', [App\Http\Controllers\Admin\BouquetController::class, 'addChannels'])->name('bouquets.channels.add');
        Route::delete('/bouquets/{bouquet}/channels/{channel}', [App\Http\Controllers\Admin\BouquetController::class, 'removeChannel'])->name('bouquets.channels.remove');
        Route::put('/bouquets/{bouquet}/channels/reorder', [App\Http\Controllers\Admin\BouquetController::class, 'updateChannelOrder'])->name('bouquets.channels.reorder');
        Route::delete('/bouquets/{bouquet}/channels/all', [App\Http\Controllers\Admin\BouquetController::class, 'deleteAllChannels'])->name('bouquets.channels.deleteAll');
        Route::post('/bouquets/{bouquet}/clone', [App\Http\Controllers\Admin\BouquetController::class, 'cloneBouquet'])->name('bouquets.clone');
        Route::get('/bouquets/{bouquet}/export', [App\Http\Controllers\Admin\BouquetController::class, 'export'])->name('bouquets.export');
        Route::post('/bouquets/{bouquet}/import', [App\Http\Controllers\Admin\BouquetController::class, 'import'])->name('bouquets.import');

        // ─── Settings ─────────────────────────────────────────────────────
        Route::get('/settings', [App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('settings.index');
        Route::put('/settings/general', [App\Http\Controllers\Admin\SettingsController::class, 'updateGeneral'])->name('settings.updateGeneral');
        Route::put('/settings/streaming', [App\Http\Controllers\Admin\SettingsController::class, 'updateStreaming'])->name('settings.updateStreaming');
        Route::put('/settings/payment', [App\Http\Controllers\Admin\SettingsController::class, 'updatePayment'])->name('settings.updatePayment');
        Route::put('/settings/notifications', [App\Http\Controllers\Admin\SettingsController::class, 'updateNotifications'])->name('settings.updateNotifications');

        // ─── Notifications ──────────────────────────────────────────────
        Route::get('/notifications/send', function () {
            return Inertia::render('Admin/Notifications/Send', [
                'users' => \App\Models\User::select('id', 'first_name', 'last_name', 'email')->get(),
                'channels' => \App\Models\Channel::select('id', 'name')->get(),
                'stats' => [
                    'sent_today' => \App\Models\Notification::where('created_at', '>=', now()->startOfDay())->count(),
                    'delivered' => \App\Models\Notification::whereNotNull('read_at')->count(),
                    'total_recipients' => \App\Models\User::count(),
                ],
            ]);
        })->name('notifications.send');
        Route::post('/notifications/send', [App\Http\Controllers\Admin\NotificationController::class, 'send'])->name('notifications.send.post');

        // ─── Logs & Analytics ─────────────────────────────────────────────
        Route::get('/logs', [App\Http\Controllers\Admin\LogController::class, 'index'])->name('logs.index');
        Route::get('/analytics', [App\Http\Controllers\Admin\AnalyticsController::class, 'index'])->name('analytics.index');
        Route::get('/analytics/export', [App\Http\Controllers\Admin\AnalyticsController::class, 'export'])->name('analytics.export');
    });
