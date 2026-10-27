<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MenuLinkType;
use App\Enums\MenuLocation;
use App\Enums\SystemPage;
use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Category;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Post;
use App\Models\Service;
use App\Support\Menus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Header, footer columns and mobile menu. Header and mobile items can have one
 * level of children (dropdowns); footer columns are flat lists.
 */
class MenuController extends Controller
{
    public const MAX_ITEMS = 40;

    public function index(): RedirectResponse
    {
        $menu = Menu::query()->where('location', MenuLocation::Header)->first() ?? Menu::query()->firstOrFail();

        return to_route('admin.menus.edit', $menu);
    }

    public function edit(Menu $menu): Response
    {
        $items = $menu->tree()->get();
        $live = Menus::targetUrls($items->concat($items->flatMap->children));

        $row = fn (MenuItem $item) => [
            'id' => $item->id,
            'label' => $item->label,
            'link_type' => $item->link_type->value,
            'linkable_id' => $item->linkable_id,
            'url' => $item->url,
            'auto_children' => $item->auto_children,
            'target_blank' => $item->target_blank,
            // Linked record is unpublished, inactive or deleted: hidden on the site.
            'broken' => $item->link_type !== MenuLinkType::Url && ! isset($live[$item->link_type->value][$item->linkable_id]),
        ];

        return Inertia::render('menus/Edit', [
            'menu' => [
                'id' => $menu->id,
                'title' => $menu->title,
                'location' => $menu->location->value,
                'location_label' => $menu->location->label(),
                'nested' => $this->allowsChildren($menu),
            ],
            'menus' => Menu::query()->withCount('items')->get()
                ->sortBy(fn (Menu $m) => array_search($m->location, MenuLocation::cases(), true))
                ->values()
                ->map(fn (Menu $m) => ['id' => $m->id, 'label' => $m->location->label(), 'items_count' => $m->items_count]),
            'items' => $items->map(fn (MenuItem $item) => [...$row($item), 'children' => $item->children->map($row)->values()])->values(),
            'targets' => $this->targets(),
        ]);
    }

    public function update(Request $request, Menu $menu): RedirectResponse
    {
        $nested = $this->allowsChildren($menu);
        $item = fn (string $p) => [
            "{$p}.label" => ['required', 'string', 'max:60'],
            "{$p}.link_type" => ['required', Rule::enum(MenuLinkType::class)],
            "{$p}.linkable_id" => ["exclude_if:{$p}.link_type,url", 'required', 'integer'],
            "{$p}.url" => ["exclude_unless:{$p}.link_type,url", 'required', 'string', 'max:2048', 'regex:#^(/|https?://|tel:|mailto:|\#)#i'],
            "{$p}.target_blank" => ['boolean'],
        ];

        $validator = validator($request->all(), [
            'title' => ['required', 'string', 'max:60'],
            'items' => ['present', 'array', 'max:'.static::MAX_ITEMS],
            ...$item('items.*'),
            'items.*.auto_children' => $nested ? ['nullable', Rule::in(['services', 'areas'])] : ['prohibited'],
            'items.*.children' => $nested ? ['nullable', 'array', 'max:30'] : ['prohibited'],
            ...$item('items.*.children.*'),
        ], [
            'items.*.url.regex' => 'Start the link with /, https://, tel: or mailto:.',
            'items.*.children.*.url.regex' => 'Start the link with /, https://, tel: or mailto:.',
            'items.*.linkable_id.required' => 'Choose what this item links to.',
            'items.*.children.*.linkable_id.required' => 'Choose what this item links to.',
            'items.*.children.prohibited' => 'Footer columns cannot have dropdowns.',
        ], [
            'items.*.label' => 'label',
            'items.*.children.*.label' => 'label',
            'items.*.url' => 'link',
            'items.*.children.*.url' => 'link',
        ]);

        $validator->after(fn (Validator $v) => $this->checkTargets($v, $request->input('items', [])));

        $data = $validator->validate();

        DB::transaction(function () use ($menu, $data) {
            $menu->update(['title' => $data['title']]);
            // Items are rebuilt in the submitted order (nothing else references them).
            $menu->items()->delete();

            foreach (array_values($data['items']) as $sort => $parent) {
                $created = $menu->items()->create($this->attributes($parent, $sort) + ['auto_children' => $parent['auto_children'] ?? null]);

                foreach (array_values($parent['children'] ?? []) as $childSort => $child) {
                    $menu->items()->create($this->attributes($child, $childSort) + ['parent_id' => $created->id]);
                }
            }
        });

        Menus::flush();
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$menu->location->label()} saved."]);

        return to_route('admin.menus.edit', $menu);
    }

    private function allowsChildren(Menu $menu): bool
    {
        return in_array($menu->location, [MenuLocation::Header, MenuLocation::Mobile], true);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function attributes(array $item, int $sort): array
    {
        $isUrl = $item['link_type'] === MenuLinkType::Url->value;

        return [
            'label' => $item['label'],
            'link_type' => $item['link_type'],
            'linkable_id' => $isUrl ? null : $item['linkable_id'],
            'url' => $isUrl ? $item['url'] : null,
            'target_blank' => (bool) ($item['target_blank'] ?? false),
            'sort' => $sort,
        ];
    }

    /** Linked records must exist (they may be drafts: those are just hidden until published). */
    private function checkTargets(Validator $validator, mixed $items): void
    {
        if (! is_array($items)) {
            return;
        }

        $models = ['page' => Page::class, 'service' => Service::class, 'area' => Area::class, 'post' => Post::class, 'category' => Category::class];

        $check = function (mixed $item, string $key) use ($validator, $models) {
            $type = is_array($item) ? ($item['link_type'] ?? null) : null;

            if (isset($models[$type]) && is_numeric($item['linkable_id'] ?? null) && ! $models[$type]::query()->whereKey($item['linkable_id'])->exists()) {
                $validator->errors()->add("{$key}.linkable_id", 'That item no longer exists. Choose another.');
            }
        };

        foreach ($items as $i => $item) {
            $check($item, "items.{$i}");

            foreach ((is_array($item) ? $item['children'] ?? [] : []) as $j => $child) {
                $check($child, "items.{$i}.children.{$j}");
            }
        }
    }

    /**
     * Records a menu item can link to.
     *
     * @return array<string, list<array{id: int, name: string, path: string|null, live: bool}>>
     */
    private function targets(): array
    {
        return [
            'page' => Page::query()->where(fn ($q) => $q->whereNull('system_key')->orWhere('system_key', '!=', SystemPage::AreaServiceTemplate))
                ->orderByRaw('system_key is null')->orderBy('title')->get()
                ->map(fn (Page $p) => ['id' => $p->id, 'name' => $p->title, 'path' => $p->publicPath(), 'live' => $p->isPublished()])->all(),
            'service' => Service::query()->ordered()->get()
                ->map(fn (Service $s) => ['id' => $s->id, 'name' => $s->title, 'path' => $s->publicPath(), 'live' => $s->is_active])->all(),
            'area' => Area::query()->ordered()->get()
                ->map(fn (Area $a) => ['id' => $a->id, 'name' => $a->name, 'path' => $a->publicPath(), 'live' => $a->is_active])->all(),
            'category' => Category::query()->ordered()->get()
                ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name, 'path' => $c->publicPath(), 'live' => true])->all(),
            'post' => Post::query()->latestFirst()->limit(200)->get(['id', 'title', 'slug', 'status', 'published_at'])
                ->map(fn (Post $p) => ['id' => $p->id, 'name' => $p->title, 'path' => $p->publicPath(), 'live' => $p->isLive()])->all(),
        ];
    }
}
