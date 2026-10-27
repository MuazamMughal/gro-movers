<?php

namespace App\Support;

use App\Enums\SystemPage;
use App\Models\Area;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Service;

/**
 * Breadcrumb trails for every inner page (none on the home page). The last
 * item is the current page and has no link. Index page labels follow the
 * admin titles of their system pages.
 *
 *   'breadcrumbs' => Breadcrumbs::service($service),
 *
 * @phpstan-type Crumb array{label: string, url: string|null}
 */
class Breadcrumbs
{
    /** @return list<Crumb> */
    public static function page(Page $page): array
    {
        if ($page->system_key === SystemPage::Home) {
            return [];
        }

        return static::trail([$page->title, null]);
    }

    /** @return list<Crumb> */
    public static function services(): array
    {
        return static::trail([static::indexLabel(SystemPage::ServicesIndex), null]);
    }

    /** @return list<Crumb> */
    public static function service(Service $service): array
    {
        return static::trail([static::indexLabel(SystemPage::ServicesIndex), '/services'], [$service->title, null]);
    }

    /** @return list<Crumb> */
    public static function areas(): array
    {
        return static::trail([static::indexLabel(SystemPage::AreasIndex), null]);
    }

    /** @return list<Crumb> */
    public static function area(Area $area): array
    {
        return static::trail([static::indexLabel(SystemPage::AreasIndex), '/service-areas'], [$area->name, null]);
    }

    /** @return list<Crumb> */
    public static function areaService(Area $area, Service $service): array
    {
        return static::trail(
            [static::indexLabel(SystemPage::AreasIndex), '/service-areas'],
            [$area->name, $area->publicPath()],
            [$service->title, null],
        );
    }

    /** @return list<Crumb> */
    public static function blog(int $page = 1): array
    {
        $label = static::indexLabel(SystemPage::BlogIndex);

        return $page > 1
            ? static::trail([$label, '/blog'], ["Page {$page}", null])
            : static::trail([$label, null]);
    }

    /** @return list<Crumb> */
    public static function category(Category $category, int $page = 1): array
    {
        $crumbs = [[static::indexLabel(SystemPage::BlogIndex), '/blog'], [$category->name, $page > 1 ? $category->publicPath() : null]];

        if ($page > 1) {
            $crumbs[] = ["Page {$page}", null];
        }

        return static::trail(...$crumbs);
    }

    /** @return list<Crumb> */
    public static function post(Post $post): array
    {
        $crumbs = [[static::indexLabel(SystemPage::BlogIndex), '/blog']];

        if ($post->category) {
            $crumbs[] = [$post->category->name, $post->category->publicPath()];
        }

        $crumbs[] = [$post->title, null];

        return static::trail(...$crumbs);
    }

    /** @return list<Crumb> */
    public static function reviews(): array
    {
        return static::trail([static::indexLabel(SystemPage::ReviewsIndex), null]);
    }

    /**
     * BreadcrumbList structured data (absolute URLs; the current page uses its canonical).
     *
     * @param  list<Crumb>  $crumbs
     * @return array<string, mixed>
     */
    public static function schema(array $crumbs, ?string $currentUrl = null): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn (array $crumb, int $i) => array_filter([
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $crumb['label'],
                'item' => $crumb['url'] !== null ? Seo::absoluteUrl($crumb['url']) : $currentUrl,
            ]), $crumbs, array_keys($crumbs)),
        ];
    }

    /**
     * Home plus the given [label, url] pairs.
     *
     * @param  array{0: string, 1: string|null}  ...$items
     * @return list<Crumb>
     */
    private static function trail(array ...$items): array
    {
        return [
            ['label' => 'Home', 'url' => '/'],
            ...array_map(fn (array $item) => ['label' => $item[0], 'url' => $item[1]], $items),
        ];
    }

    private static function indexLabel(SystemPage $key): string
    {
        return once(fn () => Page::system($key)?->title ?? $key->defaultTitle());
    }
}
