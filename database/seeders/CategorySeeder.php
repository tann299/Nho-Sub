<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['Facebook', 'facebook', 1],
            ['TikTok', 'tiktok', 2],
            ['Instagram', 'instagram', 3],
            ['YouTube', 'youtube', 4],
            ['Khác', 'khac', 5],
        ];

        foreach ($rows as [$name, $platform, $sort]) {
            Category::firstOrCreate(
                ['name' => $name, 'platform' => $platform],
                ['sort_order' => $sort, 'is_active' => true]
            );
        }
    }
}
