<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Seo\SiteIndex;
use App\Seo\SiteUrl;
use App\Support\Seo;
use App\Support\Settings;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/** /sitemap.xml, /robots.txt and the optional Search Console verification file. */
class SeoFileController extends Controller
{
    public const SITEMAP_CACHE = 'seo.sitemap';

    /**
     * Every indexable URL (noindex pages, drafts, /thank-you and pages whose
     * canonical points elsewhere are left out). Cached until content changes.
     */
    public function sitemap(): Response
    {
        $xml = Cache::rememberForever(static::SITEMAP_CACHE, fn () => static::sitemapXml());

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public static function sitemapXml(): string
    {
        $urls = SiteIndex::indexable()->map(fn (SiteUrl $u) => "  <url>\n    <loc>".e($u->url).'</loc>'
            .($u->lastmod ? "\n    <lastmod>".$u->lastmod->toAtomString().'</lastmod>' : '')
            ."\n  </url>");

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
            .$urls->implode("\n")."\n"
            .'</urlset>'."\n";
    }

    /** Forget the cached sitemap (any content change). */
    public static function flushSitemap(): void
    {
        Cache::forget(static::SITEMAP_CACHE);
    }

    /** robots.txt from Settings → SEO Tools, always ending with the Sitemap line. */
    public function robots(): Response
    {
        return response(static::robotsTxt(), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public static function robotsTxt(): string
    {
        $body = preg_replace('/^\s*Sitemap:.*$/mi', '', (string) Settings::get('seo.robots_txt', "User-agent: *\nAllow: /"));

        return rtrim(preg_replace("/\n{3,}/", "\n\n", str_replace("\r\n", "\n", $body)))."\n\nSitemap: ".Seo::absoluteUrl('/sitemap.xml')."\n";
    }

    /** google1234abcd.html (HTML-file verification), only for the file name set in Settings. */
    public function verification(string $code): Response
    {
        $file = "google{$code}.html";
        abort_unless(hash_equals((string) Settings::get('integrations.search_console_file'), $file), 404);

        return response("google-site-verification: {$file}", 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
