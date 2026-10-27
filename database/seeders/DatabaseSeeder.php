<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Base data the site needs to run, then the starter content. Every seeder
     * is idempotent and never overwrites values changed in admin.
     */
    public function run(): void
    {
        $this->call([
            SettingsSeeder::class,
            RoleSeeder::class,
            AdminUserSeeder::class,
            SystemPagesSeeder::class,
            MoveTypeSeeder::class,
            MenuSeeder::class,
            BlogCategorySeeder::class,
        ]);

        // SEO-ready starter content (once; never overwrites). SEED_CONTENT=false skips it.
        if (config('settings.seed_content')) {
            $this->call(ContentSeeder::class);
        }
    }
}
