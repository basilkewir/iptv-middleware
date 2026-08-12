<?php

namespace Tests\Integration;

use App\Models\Channel;
use App\Models\EPGProgram;
use App\Models\EPGSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EPGIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_epg_programs_for_specific_channel(): void
    {
        $channel = Channel::factory()->create(['is_active' => true]);

        EPGProgram::factory()->create([
            'channel_id' => $channel->id,
            'title' => 'Morning Show',
            'start_time' => now()->subHour(),
            'end_time' => now()->addHour(),
        ]);

        EPGProgram::factory()->create([
            'channel_id' => $channel->id,
            'title' => 'Afternoon News',
            'start_time' => now()->addHours(2),
            'end_time' => now()->addHours(3),
        ]);

        $response = $this->getJson("/api/v1/epg/{$channel->id}");

        $response->assertOk();
    }

    public function test_epg_programs_filtered_by_date_range(): void
    {
        $channel = Channel::factory()->create();

        EPGProgram::factory()->create([
            'channel_id' => $channel->id,
            'start_time' => now()->subHours(2),
            'end_time' => now()->subHour(),
        ]);

        EPGProgram::factory()->create([
            'channel_id' => $channel->id,
            'start_time' => now()->addHours(1),
            'end_time' => now()->addHours(2),
        ]);

        $response = $this->getJson('/api/v1/epg', [
            'query' => [
                'start_date' => now()->toDateTimeString(),
                'end_date' => now()->addDay()->toDateTimeString(),
            ],
        ]);

        $response->assertOk();
    }

    public function test_epg_programs_require_date_parameters(): void
    {
        $response = $this->getJson('/api/v1/epg');

        $response->assertStatus(422);
    }

    public function test_epg_programs_end_date_must_be_after_start_date(): void
    {
        $response = $this->getJson('/api/v1/epg', [
            'query' => [
                'start_date' => now()->addDay()->toDateTimeString(),
                'end_date' => now()->toDateTimeString(),
            ],
        ]);

        $response->assertStatus(422);
    }

    public function test_epg_current_programs(): void
    {
        $channel = Channel::factory()->create();

        EPGProgram::factory()->create([
            'channel_id' => $channel->id,
            'start_time' => now()->subMinutes(30),
            'end_time' => now()->addMinutes(30),
        ]);

        $response = $this->getJson('/api/v1/epg', [
            'query' => [
                'start_date' => now()->subHour()->toDateTimeString(),
                'end_date' => now()->addHour()->toDateTimeString(),
            ],
        ]);

        $response->assertOk();
    }

    public function test_epg_multiple_channels_programs(): void
    {
        $channel1 = Channel::factory()->create();
        $channel2 = Channel::factory()->create();

        EPGProgram::factory()->create([
            'channel_id' => $channel1->id,
            'start_time' => now(),
            'end_time' => now()->addHour(),
        ]);

        EPGProgram::factory()->create([
            'channel_id' => $channel2->id,
            'start_time' => now(),
            'end_time' => now()->addHour(),
        ]);

        $response = $this->getJson('/api/v1/epg', [
            'query' => [
                'start_date' => now()->subMinute()->toDateTimeString(),
                'end_date' => now()->addHour()->toDateTimeString(),
            ],
        ]);

        $response->assertOk();
    }

    public function test_epg_source_active_status(): void
    {
        $activeSource = EPGSource::factory()->create(['is_active' => true]);
        $inactiveSource = EPGSource::factory()->create(['is_active' => false]);

        $this->assertTrue($activeSource->is_active);
        $this->assertFalse($inactiveSource->is_active);
    }

    public function test_epg_program_channel_relationship(): void
    {
        $channel = Channel::factory()->create(['name' => 'Test Channel']);
        $program = EPGProgram::factory()->create([
            'channel_id' => $channel->id,
            'title' => 'Test Program',
        ]);

        $this->assertEquals('Test Channel', $program->channel->name);
    }
}
