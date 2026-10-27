<?php

namespace App\Support;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\HtmlString;

/**
 * Self-hosted font tags for the public site (Vite font build).
 *
 * The font plugin writes one @font-face per file format, so each weight is
 * declared twice (WOFF2 and WOFF) and browsers use the later, larger WOFF while
 * the preloaded WOFF2 goes unused. Every supported browser reads WOFF2, so the
 * WOFF rules are dropped.
 */
class Fonts
{
    public static function tags(string $alias = 'inter'): HtmlString
    {
        return new HtmlString(static::withoutWoff((string) Vite::fonts($alias)));
    }

    public static function withoutWoff(string $html): string
    {
        return preg_replace('/@font-face\s*\{[^}]*format\("woff"\)[^}]*\}\s*/', '', $html);
    }
}
