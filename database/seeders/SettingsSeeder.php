<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Support\Settings;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Insert missing settings with their defaults; existing values are left untouched.
     */
    public function run(): void
    {
        foreach (config('settings.defaults') as $key => $value) {
            Setting::query()->firstOrCreate(
                ['key' => $key],
                ['group' => strtok($key, '.'), 'value' => $value],
            );
        }

        Settings::flush();
    }
}
