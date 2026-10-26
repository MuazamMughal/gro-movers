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

        return $vars;
    }

    public static function css(): string
    {
        $declarations = collect(static::variables())
            ->map(fn (string $value, string $var) => "{$var}:{$value}")
            ->implode(';');

        return ":root{{$declarations}}";
    }
}
