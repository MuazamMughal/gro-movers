<?php

namespace Database\Factories;

use App\Models\SeoMeta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoMeta>
 */
class SeoMetaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'meta_title' => fake()->unique()->sentence(6),
            'meta_description' => fake()->sentence(18),
            'robots_index' => true,
            'robots_follow' => true,
        ];
    }

    public function noindex(): static
    {
        return $this->state(['robots_index' => false]);
    }
}
