<?php

namespace App\Support;

use App\Enums\MenuLinkType;
use App\Enums\MenuLocation;
use App\Models\Area;
use App\Models\Category;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Post;
use App\Models\Service;
use Illuminate\Support\Collection;
use Illuminate\Support\Once;

/**
 * Menus for the public site, with every link resolved to a live URL.
 * Links to records that are unpublished, inactive or deleted are left out,
 * so the header and footer never point to a 404.
 *
 *   @foreach (Menus::for(MenuLocation::Header) as $item) {{ $item['label'] }} {{ $item['url'] }} … @endforeach
 */
class Menus
{
    /**
     * @return list<array{label: string, url: string|null, new_tab: bool, children: list<array{label: string, url: string, new_tab: bool}>}>
     */
    public static function for(MenuLocation $location): array
    {
        return static::load($location)['items'];
    }

    /** The menu's title (footer column heading). */
    public static function title(MenuLocation $location): ?string
    {
        return static::load($location)['title'];
    }

    /** The mobile menu, or the header menu when the mobile menu is empty. */
    public static function mobile(): array
    {
        return static::for(MenuLocation::Mobile) ?: static::for(MenuLocation::Header);
    }

    /** Forget menus built earlier in this request (after saving a menu). */
    public static function flush(): void
    {
        Once::flush();
    }

    /**
     * Built once per request and location (once() is reset between requests and tests).
     *
     * @return array{title: string|null, items: list<array<string, mixed>>}
     */
    private static function load(MenuLocation $location): array
    {
        return once(fn () => static::build($location));
    }

    /**
     * @return array{title: string|null, items: list<array<string, mixed>>}
     */
    private static function build(MenuLocation $location): array
    {
        $menu = Menu::query()->where('location', $location)->first();

        if (! $menu) {
            return ['title' => null, 'items' => []];
        }

        $items = $menu->tree()->get();
        $urls = static::targetUrls($items->concat($items->flatMap->children));

        $resolved = $items->map(function (MenuItem $item) use ($urls) {
            $children = match ($item->auto_children) {
                'services' => Service::query()->active()->where('show_in_menu', true)->ordered()->get()
                    ->map(fn (Service $s) => ['label' => $s->title, 'url' => $s->publicPath(), 'new_tab' => false])->all(),
                'areas' => Area::query()->active()->where('show_in_menu', true)->ordered()->get()
                    ->map(fn (Area $a) => ['label' => $a->name, 'url' => $a->publicPath(), 'new_tab' => false])->all(),
                default => $item->children
                    ->map(fn (MenuItem $child) => static::entry($child, $urls))
                    ->filter(fn ($child) => $child['url'] !== null)
                    ->map(fn ($child) => array_diff_key($child, ['children' => true]))
                    ->values()->all(),
            };

            return [...static::entry($item, $urls), 'children' => $children];
        });

        return [
            'title' => $menu->title,
            // A parent whose own link is gone stays as a dropdown label if it still has children.
            'items' => $resolved->filter(fn ($item) => $item['url'] !== null || $item['children'] !== [])->values()->all(),
        ];
    }

    /**
     * @param  array<string, array<int, string>>  $urls
     * @return array{label: string, url: string|null, new_tab: bool, children: array}
     */
    private static function entry(MenuItem $item, array $urls): array
    {
        $url = $item->link_type === MenuLinkType::Url
            ? (filled($item->url) ? $item->url : null)
            : ($urls[$item->link_type->value][$item->linkable_id] ?? null);

        return ['label' => $item->label, 'url' => $url, 'new_tab' => $item->target_blank, 'children' => []];
    }

    /**
     * Public paths of every linked record, by link type and ID (live records only).
     *
     * @param  Collection<int, MenuItem>  $items
     * @return array<string, array<int, string>>
     */
    public static function targetUrls(Collection $items): array
    {
        $ids = $items->where('link_type', '!=', MenuLinkType::Url)->groupBy(fn (MenuItem $i) => $i->link_type->value)
            ->map(fn ($group) => $group->pluck('linkable_id')->filter()->unique()->all());

        $lookup = fn (string $type, callable $query) => isset($ids[$type])
            ? $query()->whereKey($ids[$type])->get()->mapWithKeys(fn ($record) => [$record->getKey() => $record->publicPath()])->filter()->all()
            : [];

        return [
            'page' => $lookup('page', fn () => Page::query()->published()),
            'service' => $lookup('service', fn () => Service::query()->active()),
            'area' => $lookup('area', fn () => Area::query()->active()),
            'post' => $lookup('post', fn () => Post::query()->published()),
            'category' => $lookup('category', fn () => Category::query()),
        ];
    }
}
