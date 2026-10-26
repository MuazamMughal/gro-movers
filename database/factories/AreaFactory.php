<?php

namespace Database\Factories;

use App\Models\Area;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Area>
 */
class AreaFactory extends Factory
{
    public function definition(): array
    {
        $city = fake()->unique()->city();

        return [
            'city' => $city,
            'state_name' => 'Texas',
            'state_code' => 'TX',
            'slug' => Str::slug("{$city} tx"),
            'intro' => fake()->sentence(20),
            'lat' => fake()->latitude(32.5, 33.2),
            'lng' => fake()->longitude(-97.3, -96.5),
            'zip_codes' => [fake()->numerify('75###'), fake()->numerify('75###')],
            'sort' => 0,
            'is_active' => true,
            'show_in_menu' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
