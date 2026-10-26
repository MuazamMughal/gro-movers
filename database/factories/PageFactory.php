<?php

namespace Database\Factories;

use App\Enums\PageStatus;
use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    public function definition(): array
    {
        $title = Str::title(fake()->unique()->words(3, true));

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'status' => PageStatus::Published,
            'template' => 'default',
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => PageStatus::Draft, 'published_at' => null]);
    }
}
