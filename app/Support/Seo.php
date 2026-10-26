<?php

namespace App\Support;

use App\Models\Media;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Builds the <head> SEO data for public pages from a record's admin SEO fields,
 * with fallbacks (see HasSeo) and site defaults from Settings.
 *
 *   $seo = Seo::for($service, path: $service->publicPath());
 *   $seo = Seo::for($blogIndexPage, path: '/blog', page: 2);
 *   $seo = Seo::make(title: 'Page not found', path: request()->path(), index: false);
 */
class Seo
{
    /**
     * @param  Model  $model  any model using HasSeo
     */
    public static function for(Model $model, string $path, int $page = 1): SeoData
    {
        $model->loadMissing('seo');
        $meta = $model->seo;

        $title = $model->seoTitle();
        $description = $model->seoDescription();

        if ($page > 1) {
            // Paginated pages keep their own canonical and get a distinct title.
            $title = static::withPage($title, $page);
        }

        $canonical = $page === 1 && filled($meta?->canonical_url)
            ? static::absoluteUrl($meta->canonical_url)
            : static::absoluteUrl($page > 1 ? rtrim($path, '/')."/page/{$page}" : $path);

        return new SeoData(
            title: $title,
            description: $description,
            canonical: $canonical,
            index: $model->seoIndexable(),
            follow: $model->seoFollowable(),
            ogType: $model->seoOgType(),
            ogTitle: filled($meta?->og_title) ? $meta->og_title : static::withoutSuffix($title),
            ogDescription: filled($meta?->og_description) ? Str::squish($meta->og_description) : $description,
            image: $model->seoImage(),
            siteName: static::siteName(),
        );
    }

    /** SEO data for pages without a record (404, previews). */
    public static function make(string $title, string $path, ?string $description = null, bool $index = true): SeoData
    {
        $suffix = (string) Settings::get('seo.title_suffix', '');
        $id = Settings::get('seo.default_og_image_id');

        return new SeoData(
            title: trim($title.' '.$suffix),
            description: $description ?? Settings::get('seo.default_description'),
            canonical: static::absoluteUrl($path),
            index: $index,
            follow: true,
            ogType: 'website',
            ogTitle: $title,
            ogDescription: $description ?? Settings::get('seo.default_description'),
            image: $id ? Media::query()->with('variants')->find($id) : null,
            siteName: static::siteName(),
        );
    }

    /**
     * Absolute URL on the canonical host. Paths and full URLs are both accepted;
     * production always uses https.
     */
    public static function absoluteUrl(string $pathOrUrl): string
    {
        if (preg_match('#^https?://#i', $pathOrUrl)) {
            return $pathOrUrl;
        }

        $base = parse_url((string) config('app.url'));
        $scheme = app()->isProduction() ? 'https' : ($base['scheme'] ?? 'https');
        $host = Settings::get('seo.canonical_host') ?: ($base['host'] ?? 'localhost');
        $port = ! app()->isProduction() && isset($base['port']) && ! Settings::get('seo.canonical_host') ? ':'.$base['port'] : '';

        $path = '/'.ltrim($pathOrUrl, '/');
        // No trailing slash except for the root (matches the site's URL rules).
        $path = $path === '/' ? '/' : rtrim($path, '/');

        return "{$scheme}://{$host}{$port}{$path}";
    }

    public static function siteName(): string
    {
        return (string) Settings::get('general.company_name', config('app.name'));
    }

    private static function withPage(string $title, int $page): string
    {
        $suffix = (string) Settings::get('seo.title_suffix', '');

        if ($suffix !== '' && str_ends_with($title, $suffix)) {
            return trim(Str::beforeLast($title, $suffix))." – Page {$page} {$suffix}";
        }

        return "{$title} – Page {$page}";
    }

    private static function withoutSuffix(string $title): string
    {
        $suffix = (string) Settings::get('seo.title_suffix', '');

        return $suffix !== '' && str_ends_with($title, $suffix)
            ? trim(Str::beforeLast($title, $suffix))
            : $title;
    }
}
