<?php

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        $title = fake()->sentence(4);

        return [
            'title' => $title,
            'slug' => \Illuminate\Support\Str::slug($title) . '-' . fake()->unique()->numerify('###'),
            'summary' => fake()->sentence(10),
            'body' => implode("\n\n", fake()->paragraphs(3)),
            'is_published' => true,
            'published_at' => now()->subDay(),
        ];
    }
}
