<?php

namespace App\Http\Controllers\Site;

use App\Blog\PostContent;
use App\Enums\SystemPage;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Section;
use App\Sections\AutoSections;
use App\Sections\SectionRenderer;
use App\Support\Breadcrumbs;
use App\Support\Seo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * /blog, /blog/page/N, /blog/category/{slug}(/page/N) and /blog/{slug}.
 * Pagination uses paths, not ?page=, so every listing page can be a static file.
 */
class BlogController extends Controller
{
    public const PER_PAGE = 9;

    public function __construct(private readonly SectionRenderer $renderer) {}

    public function index(Request $request, ?int $page = null): View|RedirectResponse
    {
        if ($redirect = $this->canonicalPage($request, $page, '/blog')) {
            return $redirect;
        }

        $page ??= 1;
        $blogPage = Page::system(SystemPage::BlogIndex);
        abort_unless($blogPage && $blogPage->isPublished(), 404);

        $featured = $page === 1 ? $this->cards()->where('is_featured', true)->first() : null;
        $posts = $this->paginate($this->cards()->when($featured, fn (Builder $q) => $q->whereKeyNot($featured->id)), $page);

        $sections = AutoSections::complete($blogPage->visibleSections()->get(), [
            'heading' => $blogPage->title,
            'subheading' => 'Moving tips, packing guides and honest cost breakdowns from our moving crews.',
            'theme' => 'dark',
        ], withFaq: false);
        $closing = $sections->last()?->type === 'cta_banner' ? collect([$sections->pop()]) : collect();

        return view('blog.list', [
            'seo' => Seo::for($blogPage, '/blog', $page),
            'breadcrumbs' => Breadcrumbs::blog($page),
            'intro' => $page === 1 ? $this->renderer->render($blogPage, sections: $sections) : null,
            'heading' => $page === 1 ? null : $blogPage->title,
            'closing' => $this->renderer->render($blogPage, sections: $closing, allowH1: false),
            'featured' => $featured,
            'posts' => $posts,
            'basePath' => '/blog',
            'categories' => $this->categoryNav(),
            'activeCategory' => null,
        ]);
    }

    public function category(Request $request, string $slug, ?int $page = null): View|RedirectResponse
    {
        $category = Category::query()->where('slug', $slug)->with('seo')->firstOrFail();

        if ($redirect = $this->canonicalPage($request, $page, $category->publicPath())) {
            return $redirect;
        }

        $page ??= 1;
        $posts = $this->paginate($this->cards()->where('category_id', $category->id), $page);

        $hero = AutoSections::make('hero', [
            'eyebrow' => 'Blog',
            'heading' => $category->name,
            'subheading' => $category->description,
            'theme' => 'dark',
        ], -1);

        return view('blog.list', [
            'seo' => Seo::for($category, $category->publicPath(), $page),
            'breadcrumbs' => Breadcrumbs::category($category, $page),
            'intro' => $this->renderer->render($category, sections: [$hero]),
            'heading' => null,
            'closing' => $this->renderer->render($category, sections: [$this->cta()], allowH1: false),
            'featured' => null,
            'posts' => $posts,
            'basePath' => $category->publicPath(),
            'categories' => $this->categoryNav(),
            'activeCategory' => $category->id,
        ]);
    }

    public function show(string $slug): View
    {
        $post = Post::query()->published()->where('slug', $slug)->firstOrFail();

        return $this->renderPost($post);
    }

    /** Admin preview of a saved post, whatever its status. */
    public function preview(Post $post): View
    {
        return $this->renderPost($post, preview: true);
    }

    private function renderPost(Post $post, bool $preview = false): View
    {
        $post->load(['seo', 'category', 'author.photo.variants', 'tags', 'featuredImage.variants']);

        return view('blog.show', [
            'post' => $post,
            'seo' => Seo::for($post, $post->publicPath()),
            'breadcrumbs' => Breadcrumbs::post($post),
            'body' => PostContent::render($post->content),
            'related' => $this->related($post),
            'previous' => $this->neighbour($post, older: true),
            'next' => $this->neighbour($post, older: false),
            'closing' => $this->renderer->render($post, sections: [$this->cta()], allowH1: false),
            'preview' => $preview,
            'previewNotice' => $preview ? 'Preview of the saved post ('.$post->state().'). Visitors cannot see it until it is published.' : null,
        ]);
    }

    /**
     * ?page=N becomes /…/page/N and /…/page/1 becomes the listing itself (301).
     */
    private function canonicalPage(Request $request, ?int $page, string $basePath): ?RedirectResponse
    {
        if ($request->has('page')) {
            $n = max(1, (int) $request->query('page'));

            return redirect($n === 1 ? $basePath : "{$basePath}/page/{$n}", 301);
        }

        return $page === 1 ? redirect($basePath, 301) : null;
    }

    private function cards(): Builder
    {
        return Post::query()->published()->latestFirst()->with(['category', 'featuredImage.variants']);
    }

    private function paginate(Builder $query, int $page): LengthAwarePaginator
    {
        $posts = $query->paginate(static::PER_PAGE, page: $page);

        abort_if($page > 1 && $page > $posts->lastPage(), 404);

        return $posts;
    }

    /** Categories that have live posts, for the category nav. */
    private function categoryNav(): Collection
    {
        return Category::query()->ordered()
            ->whereHas('posts', fn (Builder $q) => $q->published())
            ->get(['id', 'name', 'slug']);
    }

    /** Up to 3 live posts: same category first, then the latest others. */
    private function related(Post $post): Collection
    {
        $sameCategory = $this->cards()->where('category_id', $post->category_id)->whereKeyNot($post->id)->limit(3)->get();

        if ($sameCategory->count() === 3) {
            return $sameCategory;
        }

        $others = $this->cards()->whereKeyNot([$post->id, ...$sameCategory->modelKeys()])->limit(3 - $sameCategory->count())->get();

        return $sameCategory->concat($others);
    }

    private function neighbour(Post $post, bool $older): ?Post
    {
        if (! $post->published_at) {
            return null;
        }

        $op = $older ? '<' : '>';

        return Post::query()->published()
            ->where(fn (Builder $q) => $q->where('published_at', $op, $post->published_at)
                ->orWhere(fn (Builder $q) => $q->where('published_at', $post->published_at)->where('id', $op, $post->id)))
            ->orderBy('published_at', $older ? 'desc' : 'asc')
            ->orderBy('id', $older ? 'desc' : 'asc')
            ->first(['id', 'title', 'slug']);
    }

    private function cta(): Section
    {
        return AutoSections::make('cta_banner', [...AutoSections::CTA, 'theme' => 'dark'], -3);
    }
}
