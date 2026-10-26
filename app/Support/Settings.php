<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

/**
 * Read/write access to the key/value `settings` table, cached forever.
 * Keys are dotted and start with their group, e.g. "general.company_name".
 */
class Settings
{
    public const CACHE_KEY = 'settings.all';

    public static function get(string $key, mixed $default = null): mixed
    {
        return Arr::get(static::all(), $key, $default);
    }

    /**
     * Flat map of every setting, keyed by its dotted key.
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return Cache::rememberForever(
            static::CACHE_KEY,
            fn () => Setting::query()->pluck('value', 'key')->all(),
        );
    }

    /**
     * All settings of one group, keyed by the part after the group prefix.
     *
     * @return array<string, mixed>
     */
    public static function group(string $group): array
    {
        $prefix = $group.'.';
        $values = [];

        foreach (static::all() as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                $values[substr($key, strlen($prefix))] = $value;
            }
        }

        return $values;
    }

    public static function set(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['group' => strtok($key, '.'), 'value' => $value],
        );
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            static::set($key, $value);
        }
    }

    public static function flush(): void
    {
        Cache::forget(static::CACHE_KEY);
    }
}
