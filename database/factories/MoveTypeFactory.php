<?php

namespace Database\Factories;

use App\Models\MoveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MoveType>
 */
class MoveTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->word()).' Move',
            'sort' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
