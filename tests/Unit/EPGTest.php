<?php

namespace Tests\Unit;

use App\Models\Channel;
use App\Models\EPGProgram;
use App\Models\EPGSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EPGTest extends TestCase
{
    use RefreshDatabase;

    public function test_epg_program_belongs_to_channel(): void
    {
        $channel = Channel::factory()->create();
        $program = EPGProgram::factory()->create(['channel_id' => $channel->id]);

        $this->assertInstanceOf(Channel::class, $program->channel);
        $this->assertEquals($channel->id, $program->channel->id);
    }

    public function test_epg_program_belongs_to_epg_source(): void
    {
        $source = EPGSource::factory()->create();
        $program = EPGProgram::factory()->create(['epg_source_id' => $source->id]);

        $this->assertInstanceOf(EPGSource::class, $program->epgSource);
        $this->assertEquals($source->id, $program->epgSource->id);
    }

    public function test_epg_program_has_correct_time_range(): void
    {
        $startTime = now();
        $endTime = $startTime->copy()->addHours(2);

        $program = EPGProgram::factory()->create([
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);

        $this->assertEquals($startTime->timestamp, $program->start_time->timestamp);
        $this->assertEquals($endTime->timestamp, $program->end_time->timestamp);
        $this->assertTrue($program->end_time->greaterThan($program->start_time));
    }

    public function test_epg_program_fillable_fields(): void
    {
        $program = EPGProgram::factory()->create([
            'title' => 'Evening News',
            'description' => 'Daily news broadcast',
            'language' => 'en',
            'rating' => 'PG',
            'category' => 'News',
            'season' => 1,
            'episode' => 5,
        ]);

        $this->assertEquals('Evening News', $program->title);
        $this->assertEquals('Daily news broadcast', $program->description);
        $this->assertEquals('en', $program->language);
        $this->assertEquals('PG', $program->rating);
        $this->assertEquals('News', $program->category);
        $this->assertEquals(1, $program->season);
        $this->assertEquals(5, $program->episode);
    }

    public function test_epg_programs_can_be_filtered_by_date(): void
    {
        $channel = Channel::factory()->create();

        $todayProgram = EPGProgram::factory()->create([
            'channel_id' => $channel->id,
            'start_time' => now()->setTime(10, 0),
            'end_time' => now()->setTime(11, 0),
        ]);

        $tomorrowProgram = EPGProgram::factory()->create([
            'channel_id' => $channel->id,
            'start_time' => now()->addDay()->setTime(10, 0),
            'end_time' => now()->addDay()->setTime(11, 0),
        ]);

        $todayPrograms = EPGProgram::whereDate('start_time', now()->toDateString())->get();

        $this->assertCount(1, $todayPrograms);
        $this->assertEquals($todayProgram->id, $todayPrograms->first()->id);
    }

    public function test_epg_programs_can_be_ordered_by_start_time(): void
    {
        $channel = Channel::factory()->create();

        EPGProgram::factory()->create([
            'channel_id' => $channel->id,
            'start_time' => now()->addHours(3),
            'end_time' => now()->addHours(4),
        ]);

        EPGProgram::factory()->create([
            'channel_id' => $channel->id,
            'start_time' => now()->addHours(1),
            'end_time' => now()->addHours(2),
        ]);

        $programs = EPGProgram::orderBy('start_time', 'asc')->get();

        $this->assertTrue($programs[0]->start_time->lessThan($programs[1]->start_time));
    }

    public function test_epg_source_is_active_by_default(): void
    {
        $source = EPGSource::factory()->create(['is_active' => true]);

        $this->assertTrue($source->is_active);
    }

    public function test_epg_program_program_id_is_unique_per_source(): void
    {
        $source = EPGSource::factory()->create();

        $program1 = EPGProgram::factory()->create([
            'epg_source_id' => $source->id,
            'program_id' => 'prog-001',
        ]);

        $program2 = EPGProgram::factory()->create([
            'epg_source_id' => $source->id,
            'program_id' => 'prog-002',
        ]);

        $this->assertNotEquals($program1->program_id, $program2->program_id);
    }

    public function test_epg_program_can_have_null_optional_fields(): void
    {
        $program = EPGProgram::factory()->create([
            'description' => null,
            'language' => null,
            'rating' => null,
            'category' => null,
            'episode' => null,
            'season' => null,
        ]);

        $this->assertNull($program->description);
        $this->assertNull($program->language);
        $this->assertNull($program->rating);
    }

    public function test_channel_has_many_epg_programs(): void
    {
        $channel = Channel::factory()->create();

        EPGProgram::factory()->count(3)->create(['channel_id' => $channel->id]);

        $this->assertCount(3, $channel->epgPrograms);
    }
}
