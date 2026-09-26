<?php

namespace Tests\Unit;

use App\Console\Commands\EnsureOfflineStream;
use App\Console\Commands\PrepareOfflineStream;
use PHPUnit\Framework\TestCase;

class OfflineLoopCommandTest extends TestCase
{
    public function test_prepare_and_ensure_commands_are_registered_with_expected_signatures(): void
    {
        $prepare = new PrepareOfflineStream();
        $ensure  = new EnsureOfflineStream();

        $this->assertStringContainsString('streams:prepare-offline', $prepare->getName());
        $this->assertStringContainsString('streams:ensure-offline', $ensure->getName());
        $this->assertStringContainsString('no continuous re-encode', $prepare->getDescription());
        $this->assertStringContainsString('never re-encodes', $ensure->getDescription());
    }

    public function test_prepare_accepts_force_rebuild_flag(): void
    {
        $prepare = new PrepareOfflineStream();
        $definition = $prepare->getDefinition();

        $this->assertTrue($definition->hasOption('force-rebuild'));
        $this->assertFalse($definition->hasOption('force-encode'));
    }

    public function test_rotator_script_emits_discontinuity_on_loop_wrap(): void
    {
        $prepare = new PrepareOfflineStream();
        $ref     = new \ReflectionClass($prepare);
        $method  = $ref->getMethod('buildRotatorScript');
        $method->setAccessible(true);

        $script = $method->invoke($prepare, '/tmp/hls', ['seg_000.ts', 'seg_001.ts', 'seg_002.ts']);

        $this->assertStringContainsString('#EXT-X-DISCONTINUITY', $script);
        $this->assertStringContainsString('#EXT-X-INDEPENDENT-SEGMENTS', $script);
        // Advance MEDIA-SEQUENCE once per 2s segment, not every wall-clock second.
        $this->assertStringContainsString('sleep 2', $script);
        $this->assertStringNotContainsString('sleep 1', $script);
    }
}
