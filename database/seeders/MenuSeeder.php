<?php

namespace Database\Seeders;

use App\Enums\MenuLocation;
use App\Models\Menu;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    /**
     * Create one (empty) menu per location. Menu items are seeded in T16.
     */
    public function run(): void
    {
        foreach (MenuLocation::cases() as $location) {
            Menu::query()->firstOrCreate(
                ['location' => $location],
                ['title' => $location->label()],
            );
        }
    }
}
