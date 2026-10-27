<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * The built-in Administrator role and the default editable roles
 * (config/permissions.php). Existing roles are left as they are.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::administrator();

        foreach (config('permissions.defaults') as $name => $role) {
            Role::query()->firstOrCreate(['name' => $name], $role);
        }
    }
}
