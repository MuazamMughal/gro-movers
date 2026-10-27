<?php

namespace App\Services\Static;

use Throwable;

/**
 * Per-page critical CSS for the static cache, in PHP (cPanel has no Node).
 *
 * The site stylesheet is Tailwind output: mostly one class per rule. For each
 * page we keep the rules whose class names all appear in the page's class
 * attributes (plus every rule without a class: resets, element styles, :root
 * variables, @property, @keyframes), inline that as <style>, and load the full
 * stylesheet without blocking rendering for anything added later by JS.
 * If anything goes wrong the page is returned unchanged.
 */
class CriticalCss
{
    /** Pages whose used CSS is bigger than this keep the normal stylesheet link. */
    public const MAX_INLINE_BYTES = 40_000;

    /** @var array<string, list<array<string, mixed>>> parsed stylesheets by file */
    private static array $parsed = [];

    public static function apply(string $html): string
    {
        try {
            if (! preg_match('#<link rel="stylesheet" href="(/build/assets/site-[^"]+\.css)"\s*/?>#', $html, $link)) {
                return $html;
            }

            $file = public_path(ltrim($link[1], '/'));

            if (! is_file($file)) {
                return $html;
            }

            $css = static::extract($html, static::$parsed[$file] ??= static::parse((string) file_get_contents($file)));

            if ($css === '' || strlen($css) > static::MAX_INLINE_BYTES) {
                return $html;
            }

            $href = e($link[1]);
            $async = '<style data-critical>'.$css.'</style>'
                .'<link rel="stylesheet" href="'.$href.'" media="print" onload="this.media=\'all\'">'
                .'<noscript><link rel="stylesheet" href="'.$href.'"></noscript>';

            // The full sheet no longer blocks rendering, so it should not be fetched at high priority either.
            $html = preg_replace('#<link rel="preload" as="style" href="'.preg_quote($link[1], '#').'"\s*/?>#', '', $html);

            return str_replace($link[0], $async, $html);
        } catch (Throwable $e) {
            report($e);

            return $html;
        }
    }

    /** Forget parsed stylesheets (a new build was deployed). */
    public static function flush(): void
    {
        static::$parsed = [];
    }

    /**
     * Only the rules this page needs.
     *
     * @param  list<array<string, mixed>>  $nodes
     */
    public static function extract(string $html, array $nodes): string
    {
        $classes = [];

        preg_match_all('/\bclass\s*=\s*"([^"]*)"/i', $html, $m);
        foreach ($m[1] as $list) {
            foreach (preg_split('/\s+/', html_entity_decode($list, ENT_QUOTES | ENT_HTML5), -1, PREG_SPLIT_NO_EMPTY) as $class) {
                $classes[$class] = true;
            }
        }

        return static::render($nodes, $classes);
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @param  array<string, true>  $classes
     */
    private static function render(array $nodes, array $classes): string
    {
        $out = '';

        foreach ($nodes as $node) {
            if ($node['type'] === 'statement') {
                $out .= $node['text'];
            } elseif ($node['type'] === 'rule') {
                $selectors = array_filter($node['selectors'], fn (array $required) => static::matches($required, $classes));

                if ($selectors !== []) {
                    $out .= implode(',', array_keys($selectors)).'{'.$node['body'].'}';
                }
            } else { // block: @media, @supports, @layer, @keyframes …
                $inner = $node['keepAll'] ? $node['raw'] : static::render($node['children'], $classes);

                if ($inner !== '') {
                    $out .= $node['prelude'].'{'.$inner.'}';
                }
            }
        }

        return $out;
    }

    /** @param  list<string>  $required  @param  array<string, true>  $classes */
    private static function matches(array $required, array $classes): bool
    {
        foreach ($required as $class) {
            if (! isset($classes[$class])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Parse a (flat, already nesting-lowered) stylesheet into rules and blocks.
     *
     * @return list<array<string, mixed>>
     */
    public static function parse(string $css): array
    {
        $css = preg_replace('#/\*.*?\*/#s', '', $css);
        $pos = 0;

        return static::parseBlock($css, $pos, strlen($css));
    }

    /** @return list<array<string, mixed>> */
    private static function parseBlock(string $css, int &$pos, int $length): array
    {
        $nodes = [];

        while ($pos < $length) {
            $start = $pos;
            $next = static::nextToken($css, $pos, $length);

            if ($next === '}') {
                $pos++;

                break;
            }

            if ($next === ';' || $next === null) {
                // Statement such as "@layer theme,base;" (or trailing garbage).
                $text = trim(substr($css, $start, ($next === null ? $length : $pos + 1) - $start));
                $pos = $next === null ? $length : $pos + 1;

                if ($text !== '' && $text !== ';') {
                    $nodes[] = ['type' => 'statement', 'text' => $text];
                }

                continue;
            }

            // "{": a rule or a block.
            $prelude = trim(substr($css, $start, $pos - $start));
            $pos++;

            if (str_starts_with($prelude, '@')) {
                $name = strtolower(strtok(substr($prelude, 1), ' ({'));
                // Keyframes, font faces and property definitions are kept whole.
                $keepAll = in_array($name, ['keyframes', '-webkit-keyframes', 'font-face', 'property', 'counter-style', 'page'], true);
                $bodyStart = $pos;
                $children = $keepAll ? [] : static::parseBlock($css, $pos, $length);

                if ($keepAll) {
                    static::skipBlock($css, $pos, $length);
                }

                $nodes[] = [
                    'type' => 'block',
                    'prelude' => $prelude,
                    'keepAll' => $keepAll,
                    'raw' => $keepAll ? substr($css, $bodyStart, $pos - 1 - $bodyStart) : '',
                    'children' => $children,
                ];

                continue;
            }

            $bodyStart = $pos;
            static::skipBlock($css, $pos, $length);
            $nodes[] = [
                'type' => 'rule',
                'selectors' => static::selectors($prelude),
                'body' => substr($css, $bodyStart, $pos - 1 - $bodyStart),
            ];
        }

        return $nodes;
    }

    /**
     * Each selector of a list with the classes it needs, e.g.
     * ".md\:pb-0" => ["md:pb-0"], ".group-hover\:x:is(:where(.group):hover *)" => ["group-hover:x", "group"].
     *
     * @return array<string, list<string>>
     */
    private static function selectors(string $prelude): array
    {
        $result = [];

        foreach (static::splitSelectors($prelude) as $selector) {
            preg_match_all('/\.((?:\\\\.|[\w-])+)/u', $selector, $m);
            $result[$selector] = array_values(array_unique(array_map(fn ($c) => preg_replace('/\\\\(.)/u', '$1', $c), $m[1])));
        }

        return $result;
    }

    /** Split "a,b:is(c,d),e" at top-level commas only. @return list<string> */
    private static function splitSelectors(string $prelude): array
    {
        $parts = [];
        $depth = 0;
        $current = '';
        $length = strlen($prelude);

        for ($i = 0; $i < $length; $i++) {
            $ch = $prelude[$i];

            if ($ch === '\\' && $i + 1 < $length) {
                $current .= $ch.$prelude[++$i];

                continue;
            }

            if ($ch === '(' || $ch === '[') {
                $depth++;
            } elseif ($ch === ')' || $ch === ']') {
                $depth--;
            } elseif ($ch === ',' && $depth === 0) {
                $parts[] = trim($current);
                $current = '';

                continue;
            }

            $current .= $ch;
        }

        $parts[] = trim($current);

        return array_values(array_filter($parts, fn ($p) => $p !== ''));
    }

    /** Advance to the next unescaped, unquoted "{", "}" or ";" and return it (null at the end). */
    private static function nextToken(string $css, int &$pos, int $length): ?string
    {
        $paren = 0;

        while ($pos < $length) {
            $ch = $css[$pos];

            if ($ch === '\\') {
                $pos += 2;

                continue;
            }

            if ($ch === '"' || $ch === "'") {
                $end = strpos($css, $ch, $pos + 1);
                $pos = $end === false ? $length : $end + 1;

                continue;
            }

            if ($ch === '(') {
                $paren++;
            } elseif ($ch === ')') {
                $paren--;
            } elseif ($paren === 0 && ($ch === '{' || $ch === '}' || $ch === ';')) {
                return $ch;
            }

            $pos++;
        }

        return null;
    }

    /** Move past the matching "}" of a block whose "{" was just consumed. */
    private static function skipBlock(string $css, int &$pos, int $length): void
    {
        $depth = 1;

        while ($pos < $length && $depth > 0) {
            $token = static::nextToken($css, $pos, $length);

            if ($token === null) {
                return;
            }

            $depth += $token === '{' ? 1 : ($token === '}' ? -1 : 0);
            $pos++;
        }
    }
}
