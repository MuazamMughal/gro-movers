<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        $title = Str::title(fake()->unique()->words(2, true)).' Moving';

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'short_description' => fake()->sentence(16),
            'sort' => 0,
            'is_active' => true,
            'show_in_menu' => true,
            'show_on_home' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
