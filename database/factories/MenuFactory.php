<?php

namespace Database\Factories;

use App\Enums\MenuLocation;
use App\Models\Menu;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Menu>
 */
class MenuFactory extends Factory
{
    public function definition(): array
    {
        $location = fake()->unique()->randomElement(MenuLocation::cases());

        return [
            'location' => $location,
            'title' => $location->label(),
        ];
    }
}
