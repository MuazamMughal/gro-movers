<?php

namespace App\Support;

/**
 * Theme colors from Settings as CSS custom properties. Each value is re-checked
 * against #RRGGBB before printing, so a bad value can never inject CSS.
 */
class Theme
{
    public const COLORS = ['primary', 'secondary', 'tertiary', 'muted', 'border'];

    /**
     * @return array<string, string> e.g. ['--color-primary' => '#FFFFFF', ...]
     */
    public static function variables(): array
    {
        $defaults = config('settings.defaults');
        $vars = [];

        foreach (static::COLORS as $name) {
            $value = (string) Settings::get("theme.{$name}", $defaults["theme.{$name}"]);

            if (! preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
                $value = $defaults["theme.{$name}"];
            }

            $vars["--color-{$name}"] = strtoupper($value);
        }

        // Buttons with white text and orange text on white use a shade of the accent
        // that passes WCAG AA (4.5:1); the brand accent stays for icons and borders.
        $vars['--color-tertiary-strong'] = static::accessibleShade($vars['--color-tertiary']);

        return $vars;
    }

    /** Minimum contrast for the strong accent against white (WCAG AA is 4.5; a little margin). */
    public const STRONG_CONTRAST = 4.6;

    /**
     * The color itself if white text on it is readable, else the lightest darker
     * shade (mixed with black in 2 % steps) that is.
     */
    public static function accessibleShade(string $hex): string
    {
        [$r, $g, $b] = static::rgb($hex);

        for ($black = 0; $black <= 100; $black += 2) {
            $k = 1 - $black / 100;
            $shade = [(int) round($r * $k), (int) round($g * $k), (int) round($b * $k)];

            if (static::contrast($shade, [255, 255, 255]) >= static::STRONG_CONTRAST) {
                return sprintf('#%02X%02X%02X', ...$shade);
            }
        }

        return '#000000';
    }

    /** WCAG contrast ratio of two colors (#RRGGBB or [r, g, b]). */
    public static function contrast(string|array $a, string|array $b): float
    {
        $l1 = static::luminance(is_array($a) ? $a : static::rgb($a));
        $l2 = static::luminance(is_array($b) ? $b : static::rgb($b));

        return round((max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05), 2);
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function rgb(string $hex): array
    {
        return array_map('hexdec', str_split(ltrim($hex, '#'), 2));
    }

    /** @param  array{0: int, 1: int, 2: int}  $rgb */
    private static function luminance(array $rgb): float
    {
        $channel = function (int $v): float {
            $c = $v / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $channel($rgb[0]) + 0.7152 * $channel($rgb[1]) + 0.0722 * $channel($rgb[2]);
    }

    public static function css(): string
    {
        $declarations = collect(static::variables())
            ->map(fn (string $value, string $var) => "{$var}:{$value}")
            ->implode(';');

        return ":root{{$declarations}}";
    }
}
