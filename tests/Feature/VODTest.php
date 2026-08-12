<?php

namespace Tests\Feature;

use App\Models\ContentCategory;
use App\Models\VODContent;
use App\Models\VODMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VODTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_vod_content(): void
    {
        VODContent::factory()->count(5)->create(['is_active' => true]);

        $response = $this->getJson('/api/v1/vod');

        $response->assertOk();
        $response->assertJsonCount(5, 'data');
    }

    public function test_user_can_view_vod_content_details(): void
    {
        $content = VODContent::factory()->create([
            'is_active' => true,
            'title' => 'Test Movie',
        ]);

        $response = $this->getJson("/api/v1/vod/{$content->slug}");

        $response->assertOk();
        $response->assertJsonPath('data.content.title', 'Test Movie');
    }

    public function test_inactive_vod_content_returns_404(): void
    {
        $content = VODContent::factory()->create(['is_active' => false]);

        $response = $this->getJson("/api/v1/vod/{$content->slug}");

        $response->assertNotFound();
    }

    public function test_user_can_search_vod_content(): void
    {
        VODContent::factory()->create([
            'is_active' => true,
            'title' => 'The Matrix',
            'description' => 'A sci-fi classic',
        ]);

        VODContent::factory()->create([
            'is_active' => true,
            'title' => 'Comedy Night',
        ]);

        $response = $this->getJson('/api/v1/vod/search?q=matrix');

        $response->assertOk();
    }

    public function test_search_requires_query_parameter(): void
    {
        $response = $this->getJson('/api/v1/vod/search');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['q']);
    }

    public function test_search_requires_minimum_length(): void
    {
        $response = $this->getJson('/api/v1/vod/search?q=a');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['q']);
    }

    public function test_user_can_list_vod_categories(): void
    {
        ContentCategory::factory()->count(3)->create(['is_active' => true]);

        $response = $this->getJson('/api/v1/vod/categories');

        $response->assertOk();
    }

    public function test_vod_content_filtering_by_category(): void
    {
        $category = ContentCategory::factory()->create(['is_active' => true]);
        $otherCategory = ContentCategory::factory()->create(['is_active' => true]);

        $contentInCategory = VODContent::factory()->create(['is_active' => true]);
        $contentInCategory->categories()->attach($category);

        $contentOther = VODContent::factory()->create(['is_active' => true]);
        $contentOther->categories()->attach($otherCategory);

        $response = $this->getJson("/api/v1/vod?category={$category->slug}");

        $response->assertOk();
    }

    public function test_vod_content_filtering_by_year(): void
    {
        VODContent::factory()->create(['is_active' => true, 'year' => 2024]);
        VODContent::factory()->create(['is_active' => true, 'year' => 2023]);

        $response = $this->getJson('/api/v1/vod?year=2024');

        $response->assertOk();
    }

    public function test_vod_content_filtering_by_type(): void
    {
        VODContent::factory()->create(['is_active' => true, 'type' => 'movie']);
        VODContent::factory()->create(['is_active' => true, 'type' => 'series']);

        $response = $this->getJson('/api/v1/vod?type=movie');

        $response->assertOk();
    }

    public function test_view_count_is_incremented(): void
    {
        $content = VODContent::factory()->create([
            'is_active' => true,
            'view_count' => 0,
        ]);

        $this->getJson("/api/v1/vod/{$content->slug}");

        $this->assertDatabaseHas('vod_contents', [
            'id' => $content->id,
            'view_count' => 1,
        ]);
    }

    public function test_vod_series_returns_seasons(): void
    {
        $content = VODContent::factory()->create([
            'is_active' => true,
            'type' => 'series',
        ]);

        VODMedia::factory()->create([
            'vod_content_id' => $content->id,
        ]);

        $response = $this->getJson("/api/v1/vod/{$content->slug}/seasons");

        $response->assertOk();
    }

    public function test_non_series_content_rejects_seasons_endpoint(): void
    {
        $content = VODContent::factory()->create([
            'is_active' => true,
            'type' => 'movie',
        ]);

        $response = $this->getJson("/api/v1/vod/{$content->slug}/seasons");

        $response->assertStatus(400);
    }

    public function test_vod_content_pagination(): void
    {
        VODContent::factory()->count(25)->create(['is_active' => true]);

        $response = $this->getJson('/api/v1/vod?per_page=10');

        $response->assertOk();
    }
}
