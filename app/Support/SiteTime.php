<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * The company's local time zone (Settings → General). Dates are stored in UTC;
 * the admin types and the website shows them in this zone.
 */
class SiteTime
{
    public const ZONES = [
        'America/New_York' => 'Eastern (New York)',
        'America/Chicago' => 'Central (Dallas, Chicago)',
        'America/Denver' => 'Mountain (Denver)',
        'America/Phoenix' => 'Arizona (Phoenix, no DST)',
        'America/Los_Angeles' => 'Pacific (Los Angeles)',
        'America/Anchorage' => 'Alaska',
        'Pacific/Honolulu' => 'Hawaii',
    ];

    public static function zone(): string
    {
        $zone = (string) Settings::get('general.timezone', 'America/Chicago');

        return array_key_exists($zone, static::ZONES) ? $zone : 'America/Chicago';
    }

    /** A UTC timestamp in local time. */
    public static function local(?DateTimeInterface $date): ?CarbonImmutable
    {
        return $date ? CarbonImmutable::instance($date)->setTimezone(static::zone()) : null;
    }

    /** "2026-10-27T09:30" (datetime-local input value) in local time. */
    public static function toInput(?DateTimeInterface $date): ?string
    {
        return static::local($date)?->format('Y-m-d\TH:i');
    }

    /** A datetime-local value typed in local time, as UTC. */
    public static function fromInput(?string $value): ?CarbonImmutable
    {
        return blank($value) ? null : CarbonImmutable::parse($value, static::zone())->utc();
    }
}
