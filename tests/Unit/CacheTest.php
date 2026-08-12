<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheTest extends TestCase
{
    public function test_cache_can_store_and_retrieve_values(): void
    {
        Cache::put('test_key', 'test_value', 60);

        $this->assertEquals('test_value', Cache::get('test_key'));
    }

    public function test_cache_returns_default_for_missing_keys(): void
    {
        $value = Cache::get('nonexistent_key', 'default_value');

        $this->assertEquals('default_value', $value);
    }

    public function test_cache_can_store_arrays(): void
    {
        $data = ['channels' => [1, 2, 3], 'count' => 3];
        Cache::put('channels_data', $data, 60);

        $this->assertEquals($data, Cache::get('channels_data'));
    }

    public function test_cache_can_store_objects(): void
    {
        $obj = new \stdClass();
        $obj->name = 'Test';
        Cache::put('test_object', $obj, 60);

        $cached = Cache::get('test_object');
        $this->assertEquals('Test', $cached->name);
    }

    public function test_cache_can_increment_values(): void
    {
        Cache::put('counter', 0, 60);
        Cache::increment('counter');

        $this->assertEquals(1, Cache::get('counter'));
    }

    public function test_cache_can_decrement_values(): void
    {
        Cache::put('counter', 5, 60);
        Cache::decrement('counter');

        $this->assertEquals(4, Cache::get('counter'));
    }

    public function test_cache_can_forget_keys(): void
    {
        Cache::put('forgettable', 'value', 60);
        $this->assertNotNull(Cache::get('forgettable'));

        Cache::forget('forgettable');
        $this->assertNull(Cache::get('forgettable'));
    }

    public function test_cache_can_flush_all(): void
    {
        Cache::put('key1', 'value1', 60);
        Cache::put('key2', 'value2', 60);

        Cache::flush();

        $this->assertNull(Cache::get('key1'));
        $this->assertNull(Cache::get('key2'));
    }

    public function test_cache_has_method(): void
    {
        Cache::put('exists', 'value', 60);

        $this->assertTrue(Cache::has('exists'));
        $this->assertFalse(Cache::has('does_not_exist'));
    }

    public function test_cache_store_returns_value_once(): void
    {
        $callCount = 0;
        $value = Cache::remember('remember_key', 60, function () use (&$callCount) {
            $callCount++;
            return 'computed_value';
        });

        $this->assertEquals('computed_value', $value);
        $this->assertEquals(1, $callCount);

        $value2 = Cache::remember('remember_key', 60, function () use (&$callCount) {
            $callCount++;
            return 'computed_value_2';
        });

        $this->assertEquals('computed_value', $value2);
        $this->assertEquals(1, $callCount);
    }

    public function test_cache_key_prefix_is_applied(): void
    {
        $prefix = config('cache.prefix', '');
        Cache::put('prefixed_test', 'value', 60);

        $this->assertStringStartsWith($prefix, 'prefixed_test');
    }

    public function test_epg_cache_ttl(): void
    {
        $ttl = config('epg.cache.ttl', 3600);
        $this->assertIsInt($ttl);
        $this->assertGreaterThan(0, $ttl);
    }

    public function test_streaming_config_cache_settings(): void
    {
        $this->assertIsBool(config('streaming.monitoring.enabled', true));
        $this->assertIsInt(config('streaming.monitoring.metrics_retention', 30));
    }
}
