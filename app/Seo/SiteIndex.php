<?php

namespace App\Seo;

use App\Enums\SystemPage;
use App\Models\Area;
use App\Models\AreaServicePage;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Service;
use App\Services\TokenReplacer;
use App\Support\Seo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Every public URL of the site with what Google sees: title, description,
 * H1, robots and last change. Shared by the sitemap, the SEO report, the
 * duplicate check in the admin SEO panel and the SEO tests.
 *
 * H1s follow the templates: the first hero's heading when the page has one,
 * else the automatic heading (title, "Movers in {city}, {state}" …).
 */
class SiteIndex
{
    /**
     * @return Collection<int, SiteUrl>
     */
    public static function all(): Collection
    {
        return once(fn () => collect()
            ->concat(static::pages())
            ->concat(static::services())
            ->concat(static::areas())
            ->concat(static::areaServices())
            ->concat(static::blog())
            ->values());
    }

    /**
     * URLs that belong in the sitemap: indexable and canonical to themselves.
     *
     * @return Collection<int, SiteUrl>
     */
    public static function indexable(): Collection
    {
        return static::all()->filter(fn (SiteUrl $u) => $u->index && $u->selfCanonical())->values();
    }

    /** @return list<SiteUrl> */
    private static function pages(): array
    {
        $fixed = [
            SystemPage::ServicesIndex->value => '/services',
            SystemPage::AreasIndex->value => '/service-areas',
            SystemPage::ReviewsIndex->value => '/reviews',
            SystemPage::BlogIndex->value => '/blog',
        ];

        return Page::query()->published()->with(['seo', 'visibleSections'])->get()
            ->filter(fn (Page $p) => $p->publicPath() !== null)
            ->map(fn (Page $p) => static::entry(
                $p,
                $fixed[$p->system_key?->value] ?? $p->publicPath(),
                h1: static::heroHeading($p) ?? $p->title,
                type: 'page',
                label: $p->title,
                edit: route('admin.pages.edit', $p, false),
            ))
            ->values()
            ->all();
    }

    /** @return list<SiteUrl> */
    private static function services(): array
    {
        return Service::query()->active()->ordered()->with(['seo', 'visibleSections'])->get()
            ->map(fn (Service $s) => static::entry($s, $s->publicPath(), static::heroHeading($s) ?? $s->title, 'service', $s->title, route('admin.services.edit', $s, false)))
            ->all();
    }

    /** @return list<SiteUrl> */
    private static function areas(): array
    {
        return Area::query()->active()->ordered()->with(['seo', 'visibleSections'])->get()
            ->map(fn (Area $a) => static::entry($a, $a->publicPath(), static::heroHeading($a, $a) ?? "Movers in {$a->city}, {$a->state_code}", 'area', $a->name, route('admin.areas.edit', $a, false)))
            ->all();
    }

    /** City × service pages that are live: page on, city and service active, service offered there. */
    private static function areaServices(): array
    {
        return AreaServicePage::query()
            ->active()
            ->whereHas('area', fn ($q) => $q->active())
            ->whereHas('service', fn ($q) => $q->active())
            ->whereExists(fn ($q) => $q->from('area_service')->whereColumn('area_service.area_id', 'area_service_pages.area_id')->whereColumn('area_service.service_id', 'area_service_pages.service_id'))
            ->with(['area', 'service', 'seo', 'visibleSections'])
            ->get()
            ->map(function (AreaServicePage $asp) {
                $owner = $asp->contentOwner();
                $asp->resolveSeo();

                return static::entry(
                    $asp,
                    $asp->publicPath(),
                    static::heroHeading($owner, $asp->area, $asp->service) ?? $asp->heading(),
                    'area_service',
                    $asp->heading(),
                    route('admin.area-services.edit', $asp, false),
                );
            })
            ->all();
    }

    /** @return list<SiteUrl> */
    private static function blog(): array
    {
        $posts = Post::query()->published()->with(['seo', 'category'])->latestFirst()->get();
        $liveCategories = $posts->pluck('category_id')->unique();

        return [
            ...Category::query()->ordered()->with('seo')->whereIn('id', $liveCategories)->get()
                ->map(fn (Category $c) => static::entry($c, $c->publicPath(), $c->name, 'category', $c->name, route('admin.blog-categories.edit', $c, false), $posts->where('category_id', $c->id)->max('published_at'))),
            ...$posts->map(fn (Post $p) => static::entry($p, $p->publicPath(), $p->title, 'post', $p->title, route('admin.posts.edit', $p, false))),
        ];
    }

    private static function entry(Model $model, string $path, string $h1, string $type, string $label, string $edit, mixed $lastmod = null): SiteUrl
    {
        $seo = Seo::for($model, $path);

        return new SiteUrl(
            path: $path,
            url: Seo::absoluteUrl($path),
            type: $type,
            label: $label,
            editUrl: $edit,
            title: $seo->title,
            description: $seo->description,
            h1: trim($h1),
            canonical: $seo->canonical,
            index: $seo->index,
            follow: $seo->follow,
            customTitle: filled($model->seo?->meta_title),
            customDescription: filled($model->seo?->meta_description),
            lastmod: collect([$model->updated_at, $lastmod])->filter()->max(),
            model: $model,
        );
    }

    /** Heading of the record's first visible hero section, with tokens filled in. */
    private static function heroHeading(Model $owner, ?Area $area = null, ?Service $service = null): ?string
    {
        $hero = $owner->visibleSections->firstWhere('type', 'hero');
        $heading = $hero?->data['heading'] ?? null;

        return filled($heading) ? (new TokenReplacer($area, $service))->replace($heading) : null;
    }
}
