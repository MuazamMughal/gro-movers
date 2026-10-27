<?php

namespace App\Services\Static;

/**
 * Safe HTML minification for the static cache: removes comments and collapses
 * whitespace, leaving <pre>, <textarea>, <script> and <style> untouched.
 * Whitespace runs become a single space, never nothing, so the page renders identically.
 */
class HtmlMinifier
{
    public static function minify(string $html): string
    {
        $kept = [];

        // Park the blocks whose whitespace matters.
        $html = preg_replace_callback('#<(pre|textarea|script|style)\b[^>]*>.*?</\1>#is', function ($m) use (&$kept) {
            $kept[] = $m[0];

            return '%%KEEP'.(count($kept) - 1).'%%';
        }, $html);

        // Comments (but not IE conditionals, which nothing here uses anyway).
        $html = preg_replace('/<!--(?!\[if).*?-->/s', '', $html);

        // Any run of whitespace (newlines, indentation) becomes one space: the browser
        // renders that exactly like the original, so nothing can move or join.
        $html = preg_replace('/\s+/', ' ', $html);

        return trim(preg_replace_callback('/%%KEEP(\d+)%%/', fn ($m) => $kept[(int) $m[1]], $html))."\n";
    }
}
