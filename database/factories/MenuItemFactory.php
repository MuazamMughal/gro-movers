<?php

namespace Database\Factories;

use App\Enums\MenuLinkType;
use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'menu_id' => Menu::factory(),
            'label' => ucfirst(fake()->word()),
            'link_type' => MenuLinkType::Url,
            'url' => '/'.fake()->slug(2),
            'target_blank' => false,
            'sort' => 0,
        ];
    }
}
