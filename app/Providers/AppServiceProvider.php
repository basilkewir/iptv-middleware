<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\StreamingService\StreamManager;
use App\Services\StreamingService\HLSStreamer;
use App\Services\StreamingService\RTMPStreamer;
use App\Services\StreamingService\LoadBalancer;
use App\Services\EPGService\EPGManager;
use App\Services\EPGService\XMLTVParser;
use App\Services\EPGService\EPGCache;
use App\Services\PaymentService\PaymentManager;
use App\Services\PaymentService\StripeProvider;
use App\Services\PaymentService\PayPalProvider;
use App\Services\ContentService\ContentManager;
use App\Services\ContentService\VODImporter;
use App\Services\ContentService\ContentIndexer;
use App\Services\CacheService\CacheManager;
use App\Services\CacheService\RedisCache;
use App\Services\VOD\VODService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Model;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerStreamingServices();
        $this->registerEPGServices();
        $this->registerPaymentServices();
        $this->registerContentServices();
        $this->registerCacheServices();
        $this->registerVODService();
    }

    public function boot(): void
    {
        Model::unguard(false);
        Model::preventLazyLoading(!$this->app->isProduction());
    }

    private function registerStreamingServices(): void
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
    }

    private function registerEPGServices(): void
    {
        $this->app->singleton(EPGCache::class);
        $this->app->singleton(XMLTVParser::class);
        $this->app->singleton(EPGManager::class, function ($app) {
            return new EPGManager(
                $app->make(XMLTVParser::class),
                $app->make(EPGCache::class)
            );
        });
    }

    private function registerPaymentServices(): void
    {
        $this->app->singleton(StripeProvider::class);
        $this->app->singleton(PayPalProvider::class);
        $this->app->singleton(PaymentManager::class, function ($app) {
            $manager = new PaymentManager();
            return $manager;
        });
    }

    private function registerContentServices(): void
    {
        $this->app->singleton(ContentIndexer::class);
        $this->app->singleton(VODImporter::class);
        $this->app->singleton(ContentManager::class, function ($app) {
            return new ContentManager(
                $app->make(VODImporter::class),
                $app->make(ContentIndexer::class)
            );
        });
    }

    private function registerCacheServices(): void
    {
        $this->app->singleton(RedisCache::class);
        $this->app->singleton(CacheManager::class, function ($app) {
            return new CacheManager(
                $app->make(RedisCache::class)
            );
        });
    }

    private function registerVODService(): void
    {
        $this->app->singleton(VODService::class, function ($app) {
            return new VODService(
                $app->make(\App\Services\TMDB\TMDBService::class),
                $app->make(\App\Services\QualityDetectionService::class)
            );
        });
    }
}
