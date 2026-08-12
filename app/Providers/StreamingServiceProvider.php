<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\StreamingService\StreamManager;
use App\Services\StreamingService\HLSStreamer;
use App\Services\StreamingService\RTMPStreamer;
use App\Services\StreamingService\LoadBalancer;
use App\Services\StreamingService\StreamMonitor;
use App\Services\StreamingService\StreamAnalyzer;
use Illuminate\Support\ServiceProvider;

class StreamingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LoadBalancer::class);
        $this->app->singleton(HLSStreamer::class);
        $this->app->singleton(RTMPStreamer::class);
        $this->app->singleton(StreamManager::class, function ($app) {
            return new StreamManager(
                $app->make(HLSStreamer::class),
                $app->make(RTMPStreamer::class),
                $app->make(LoadBalancer::class)
            );
        });

        $this->app->singleton(StreamMonitor::class, function ($app) {
            return new StreamMonitor(
                $app->make(StreamManager::class),
                $app->make(LoadBalancer::class)
            );
        });

        $this->app->singleton(StreamAnalyzer::class, function ($app) {
            return new StreamAnalyzer(
                $app->make(StreamManager::class)
            );
        });
    }

    public function boot(): void
    {
    }
}
