<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

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
        try {
            return Cache::rememberForever(
                static::CACHE_KEY,
                fn () => Setting::query()->pluck('value', 'key')->all(),
            );
        } catch (QueryException) {
            // Table not migrated yet (fresh install, early boot): behave as empty, cache nothing.
            return [];
        }
    }

    /** Decrypted value of a secret setting (stored with setSecret), or null. */
    public static function secret(string $key): ?string
    {
        $value = static::get($key);

        if (blank($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }

    public static function setSecret(string $key, ?string $plain): void
    {
        static::set($key, blank($plain) ? null : Crypt::encryptString($plain));
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
