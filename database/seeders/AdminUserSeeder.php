<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Create the initial admin from ADMIN_* env values. If no password is configured,
     * a random one is generated and printed once.
     */
    public function run(): void
    {
        $email = config('settings.admin.email');

        if (blank($email)) {
            $this->command?->warn('ADMIN_EMAIL is not set; skipping admin user.');

            return;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->command?->info("Admin {$email} already exists; left unchanged.");

            return;
        }

        $password = config('settings.admin.password');
        $generated = blank($password);

        if ($generated) {
            $password = Str::password(20);
        }

        User::query()->create([
            'name' => config('settings.admin.name'),
            'email' => $email,
            'password' => $password,
            'role_id' => Role::administrator()->id,
        ])->forceFill(['email_verified_at' => now()])->save();

        $this->command?->info("Admin {$email} created.");

        if ($generated) {
            $this->command?->warn("Generated admin password (shown once): {$password}");
        }
    }
}
