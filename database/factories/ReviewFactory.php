<?php

namespace Database\Factories;

use App\Enums\ReviewSource;
use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->firstName().' '.fake()->lastName()[0].'.',
            'city' => fake()->randomElement(['Dallas', 'Plano', 'Irving']),
            'rating' => fake()->numberBetween(4, 5),
            'body' => fake()->paragraph(),
            'source' => fake()->randomElement(ReviewSource::cases()),
            'reviewed_at' => fake()->dateTimeBetween('-1 year'),
            'is_featured' => false,
            'is_active' => true,
            'sort' => 0,
        ];
    }

    public function featured(): static
    {
        return $this->state(['is_featured' => true]);
    }
}
