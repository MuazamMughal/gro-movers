<?php

namespace App\Support;

use App\Enums\SystemPage;
use App\Models\Page;

/**
 * "Get a Free Quote" links land on the form itself, not the top of the quote
 * page: every link to the quote page gets the #quote-form anchor, which the
 * first quote form on that page carries (components/site/quote-form).
 */
class QuoteLink
{
    public const ANCHOR = 'quote-form';

    /** The quote page path, e.g. "/get-a-quote" (follows a changed slug). */
    public static function path(): string
    {
        return once(fn () => Page::system(SystemPage::Quote)?->publicPath() ?? '/get-a-quote');
    }

    public static function href(): string
    {
        return static::path().'#'.static::ANCHOR;
    }

    /** Add the anchor to a link that points at the quote page; other links are returned unchanged. */
    public static function resolve(?string $href): ?string
    {
        if ($href === null || str_contains($href, '#') || str_contains($href, '?')) {
            return $href;
        }

        $path = parse_url($href, PHP_URL_PATH);
        $host = parse_url($href, PHP_URL_HOST);

        if ($host !== null && $host !== parse_url((string) config('app.url'), PHP_URL_HOST)) {
            return $href;
        }

        return $path !== null && rtrim($path, '/') === static::path() ? $href.'#'.static::ANCHOR : $href;
    }

    /** True the first time it is asked during a page render: that form gets the anchor. */
    public static function claimAnchor(): bool
    {
        $request = request();

        if ($request->attributes->get('quote-anchor')) {
            return false;
        }

        $request->attributes->set('quote-anchor', true);

        return true;
    }
}
