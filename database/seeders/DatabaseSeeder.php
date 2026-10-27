<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Base data the site needs to run. Every seeder is idempotent and never
     * overwrites values changed in admin. Demo/SEO content is added in T16.
     */
    public function run(): void
    {
        $this->call([
            SettingsSeeder::class,
            AdminUserSeeder::class,
            SystemPagesSeeder::class,
            MoveTypeSeeder::class,
            MenuSeeder::class,
            BlogCategorySeeder::class,
        ]);
    }
}
