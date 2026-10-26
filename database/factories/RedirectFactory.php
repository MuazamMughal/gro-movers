<?php

namespace Database\Factories;

use App\Models\Redirect;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Redirect>
 */
class RedirectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'from_path' => '/'.fake()->unique()->slug(3),
            'to_path' => '/'.fake()->slug(3),
            'status_code' => 301,
            'is_active' => true,
        ];
    }
}
