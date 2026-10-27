<?php

namespace App\Support;

use App\Models\Area;
use App\Models\Page;
use App\Models\Post;
use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * "Did you mean…?" links for the 404 page: live pages whose URL or title
 * shares words with the URL that was not found.
 */
class NotFoundSuggestions
{
    /** Words too common in this site's URLs to be useful matches. */
    private const STOP_WORDS = ['the', 'and', 'for', 'with', 'blog', 'page', 'services', 'service', 'areas', 'area', 'category', 'html', 'php', 'www'];

    /**
     * @return list<array{label: string, url: string}>
     */
    public static function for(string $path, int $limit = 5): array
    {
        $words = collect(preg_split('/[^a-z0-9]+/', Str::lower(urldecode($path))))
            ->filter(fn ($w) => strlen($w) >= 3 && ! in_array($w, static::STOP_WORDS, true))
            ->unique()
            ->take(6)
            ->values();

        if ($words->isEmpty()) {
            return [];
        }

        $matching = fn (array $columns) => fn (Builder $q) => $q->where(function (Builder $q) use ($words, $columns) {
            foreach ($words as $word) {
                foreach ($columns as $column) {
                    $q->orWhere($column, 'like', '%'.$word.'%');
                }
            }
        });

        $suggestions = collect()
            ->concat(Service::query()->active()->tap($matching(['slug', 'title']))->ordered()->limit($limit)->get()
                ->map(fn (Service $s) => ['label' => $s->title, 'url' => $s->publicPath()]))
            ->concat(Area::query()->active()->tap($matching(['slug', 'city']))->ordered()->limit($limit)->get()
                ->map(fn (Area $a) => ['label' => "Movers in {$a->name}", 'url' => $a->publicPath()]))
            ->concat(Page::query()->published()->tap($matching(['slug', 'title']))->limit($limit)->get()
                ->filter(fn (Page $p) => $p->publicPath() !== null && $p->seoIndexable())
                ->map(fn (Page $p) => ['label' => $p->title, 'url' => $p->publicPath()]))
            ->concat(Post::query()->published()->tap($matching(['slug', 'title']))->latestFirst()->limit($limit)->get()
                ->map(fn (Post $p) => ['label' => $p->title, 'url' => $p->publicPath()]));

        // Most shared words first.
        return $suggestions
            ->unique('url')
            ->sortByDesc(fn ($s) => $words->filter(fn ($w) => str_contains(Str::lower($s['label'].' '.$s['url']), $w))->count())
            ->take($limit)
            ->values()
            ->all();
    }
}
