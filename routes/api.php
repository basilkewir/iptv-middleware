<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChannelController;
use App\Http\Controllers\Api\VODController;
use App\Http\Controllers\Api\EPGController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\WatchHistoryController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\LicenseController;

// Public endpoint for HMS to fetch channel list (used for default channel picker)
Route::get('/hms/channels', function () {
    $channels = \App\Models\Channel::where('is_active', true)
        ->orderBy('channel_number')
        ->get(['id', 'name', 'slug', 'channel_number', 'logo_url'])
        ->map(fn($c) => [
            'id'             => $c->id,
            'name'           => $c->name,
            'channel_number' => $c->channel_number,
            'logo_url'       => $c->logo_url,
        ]);
    return response()->json(['success' => true, 'channels' => $channels]);
});

Route::group([], function () {

    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/auth/me', [AuthController::class, 'me'])->middleware('auth:sanctum');

    Route::get('/channels', [ChannelController::class, 'index']);
    Route::get('/channels/categories', [ChannelController::class, 'categories']);
    Route::get('/channels/{channel}', [ChannelController::class, 'show']);

    Route::get('/vod', [VODController::class, 'index']);
    Route::get('/vod/categories', [VODController::class, 'categories']);
    Route::get('/vod/genres', [VODController::class, 'genres']);
    Route::get('/vod/latest', [VODController::class, 'latest']);
    Route::get('/vod/featured', [VODController::class, 'featured']);
    Route::get('/vod/search', [VODController::class, 'search']);
    Route::get('/vod/{vod}', [VODController::class, 'show']);
    Route::get('/vod/{vod}/similar', [VODController::class, 'similar']);
    Route::get('/vod/{vod}/seasons', [VODController::class, 'seasons']);

    Route::get('/epg/{channel}', [EPGController::class, 'channelEPG']);
    Route::get('/epg', [EPGController::class, 'programs']);
    Route::get('/epg/current', [EPGController::class, 'current']);
    Route::get('/epg/upcoming', [EPGController::class, 'upcoming']);

    // License validation endpoint (POST only, no middleware - public for initial license check)
    Route::post('/license/validate', [LicenseController::class, 'validate']);

    // License validation middleware applied to all routes below
    Route::middleware('license')->group(function () {
        Route::match(['GET', 'HEAD'], '/user/profile', [ProfileController::class, 'show']);
        Route::put('/user/profile', [ProfileController::class, 'update']);

        Route::match(['GET', 'POST'], '/favorites', [FavoriteController::class, 'index']);
        Route::post('/favorites/store', [FavoriteController::class, 'store']);
        Route::delete('/favorites/{id}', [FavoriteController::class, 'destroy']);

        Route::match(['GET', 'POST'], '/watch-history', [WatchHistoryController::class, 'index']);
        Route::post('/watch-history', [WatchHistoryController::class, 'store']);
        Route::put('/watch-history/{id}', [WatchHistoryController::class, 'update']);

        Route::match(['GET', 'POST'], '/reviews/vod/{vod}', [ReviewController::class, 'index']);
        Route::post('/reviews', [ReviewController::class, 'store']);

        Route::match(['GET', 'POST'], '/subscription', [SubscriptionController::class, 'current']);
        Route::match(['GET', 'POST'], '/subscription/packages', [SubscriptionController::class, 'packages']);
        Route::match(['GET', 'POST'], '/subscription/current', [SubscriptionController::class, 'current']);
        Route::match(['POST'], '/subscription/subscribe', [SubscriptionController::class, 'subscribe']);
        Route::match(['POST'], '/subscription/{package}/subscribe', [SubscriptionController::class, 'subscribe']);
        Route::match(['POST'], '/subscription/renew', [SubscriptionController::class, 'renew']);
        Route::match(['GET'], '/subscription/history', [SubscriptionController::class, 'history']);

        Route::match(['GET', 'POST'], '/payment/methods', [PaymentController::class, 'methods']);
        Route::match(['POST'], '/payment/invoice', [PaymentController::class, 'createInvoice']);
        Route::match(['POST'], '/payment/pay/{invoice}', [PaymentController::class, 'payInvoice']);
        Route::match(['GET'], '/payment/invoices', [PaymentController::class, 'invoices']);

        Route::match(['GET'], '/admin/servers', [\App\Http\Controllers\Api\AdminServerController::class, 'index']);
    });
});