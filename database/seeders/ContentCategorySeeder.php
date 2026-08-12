<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContentCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Sports',
                'slug' => 'sports',
                'icon' => 'sports',
                'sort_order' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Movies',
                'slug' => 'movies',
                'icon' => 'movies',
                'sort_order' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'News',
                'slug' => 'news',
                'icon' => 'news',
                'sort_order' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Entertainment',
                'slug' => 'entertainment',
                'icon' => 'entertainment',
                'sort_order' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Kids',
                'slug' => 'kids',
                'icon' => 'kids',
                'sort_order' => 5,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Music',
                'slug' => 'music',
                'icon' => 'music',
                'sort_order' => 6,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Documentary',
                'slug' => 'documentary',
                'icon' => 'documentary',
                'sort_order' => 7,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Lifestyle',
                'slug' => 'lifestyle',
                'icon' => 'lifestyle',
                'sort_order' => 8,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Religious',
                'slug' => 'religious',
                'icon' => 'religious',
                'sort_order' => 9,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'International',
                'slug' => 'international',
                'icon' => 'international',
                'sort_order' => 10,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('content_categories')->insert($categories);

        $subcategories = [
            ['name' => 'Football', 'slug' => 'football', 'parent_slug' => 'sports', 'sort_order' => 1],
            ['name' => 'Basketball', 'slug' => 'basketball', 'parent_slug' => 'sports', 'sort_order' => 2],
            ['name' => 'Tennis', 'slug' => 'tennis', 'parent_slug' => 'sports', 'sort_order' => 3],
            ['name' => 'Boxing', 'slug' => 'boxing', 'parent_slug' => 'sports', 'sort_order' => 4],
            ['name' => 'Action', 'slug' => 'action-movies', 'parent_slug' => 'movies', 'sort_order' => 1],
            ['name' => 'Comedy', 'slug' => 'comedy-movies', 'parent_slug' => 'movies', 'sort_order' => 2],
            ['name' => 'Drama', 'slug' => 'drama-movies', 'parent_slug' => 'movies', 'sort_order' => 3],
            ['name' => 'Horror', 'slug' => 'horror-movies', 'parent_slug' => 'movies', 'sort_order' => 4],
            ['name' => 'Sci-Fi', 'slug' => 'scifi-movies', 'parent_slug' => 'movies', 'sort_order' => 5],
            ['name' => 'Cartoons', 'slug' => 'cartoons', 'parent_slug' => 'kids', 'sort_order' => 1],
            ['name' => 'Educational', 'slug' => 'educational-kids', 'parent_slug' => 'kids', 'sort_order' => 2],
            ['name' => 'Nature', 'slug' => 'nature-docs', 'parent_slug' => 'documentary', 'sort_order' => 1],
            ['name' => 'History', 'slug' => 'history-docs', 'parent_slug' => 'documentary', 'sort_order' => 2],
            ['name' => 'Science', 'slug' => 'science-docs', 'parent_slug' => 'documentary', 'sort_order' => 3],
        ];

        foreach ($subcategories as $sub) {
            $parent = DB::table('content_categories')->where('slug', $sub['parent_slug'])->first();
            if ($parent) {
                DB::table('content_categories')->insert([
                    'name' => $sub['name'],
                    'slug' => $sub['slug'],
                    'parent_id' => $parent->id,
                    'icon' => $sub['slug'],
                    'sort_order' => $sub['sort_order'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
