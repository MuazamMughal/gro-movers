<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An admin role: a named set of permissions from config/permissions.php.
 * The system Administrator role implies every permission.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property list<string> $permissions
 * @property bool $is_system
 */
#[Fillable(['name', 'description', 'permissions'])]
class Role extends Model
{
    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'is_system' => 'boolean',
        ];
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public static function administrator(): self
    {
        return static::query()->firstOrCreate(
            ['is_system' => true],
            ['name' => 'Administrator', 'description' => 'Full access, including users and roles.', 'permissions' => []],
        );
    }

    /** @return list<string> every permission key in config/permissions.php */
    public static function allPermissions(): array
    {
        return array_keys(array_merge(...array_values(config('permissions.groups'))));
    }

    /** @return list<string> */
    public function effectivePermissions(): array
    {
        return $this->is_system ? static::allPermissions() : array_values(array_intersect($this->permissions ?? [], static::allPermissions()));
    }

    public function allows(string $permission): bool
    {
        return in_array($permission, $this->effectivePermissions(), true);
    }
}
