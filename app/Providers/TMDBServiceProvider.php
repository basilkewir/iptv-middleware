<?php

namespace App\Providers;

use App\Services\TMDB\TMDBService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class TMDBServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TMDBService::class, function ($app) {
            return new TMDBService();
        });
    }

    public function boot(): void
    {
        // Only warn if neither env nor DB key is available (checked lazily by TMDBService)
    }
}
