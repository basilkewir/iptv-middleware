<?php

namespace Database\Factories;

use App\Models\ContentCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ContentCategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->word();
        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'parent_id' => null,
            'icon' => Str::slug($name),
            'banner_image' => null,
            'color' => fake()->hexColor(),
            'category_type' => fake()->randomElement(['live', 'vod', 'series']),
            'auto_assign_channels' => false,
            'auto_assign_vod' => false,
            'include_in_m3u' => true,
            'include_in_xmltv' => true,
            'sort_order' => fake()->numberBetween(1, 100),
            'is_active' => true,
        ];
    }
}
