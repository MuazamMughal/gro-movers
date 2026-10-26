<?php

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Models\Author;
use App\Models\Category;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    public function definition(): array
    {
        $title = rtrim(fake()->unique()->sentence(6), '.');

        return [
            'category_id' => Category::factory(),
            'author_id' => Author::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => fake()->sentence(20),
            'content' => collect(fake()->paragraphs(6))->map(fn ($p) => "<p>{$p}</p>")->implode("\n"),
            'status' => PostStatus::Published,
            'published_at' => now()->subDays(fake()->numberBetween(1, 120)),
            'is_featured' => false,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => PostStatus::Draft, 'published_at' => null]);
    }

    public function scheduled(?\DateTimeInterface $at = null): static
    {
        return $this->state(['status' => PostStatus::Scheduled, 'published_at' => $at ?? now()->addDay()]);
    }
}
