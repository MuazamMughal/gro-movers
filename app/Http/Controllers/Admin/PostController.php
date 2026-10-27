<?php

namespace App\Http\Controllers\Admin;

use App\Blog\PostContent;
use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\Author;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Support\SeoForm;
use App\Support\SiteTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Blog posts at /blog/{slug}. Saving as "Published" with a future date schedules
 * the post: it goes live by itself once that time passes.
 */
class PostController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', Rule::in(['draft', 'scheduled', 'published'])],
            'category' => ['nullable', 'integer'],
        ]);

        $posts = Post::query()
            ->with(['category', 'author', 'seo', 'featuredImage.variants'])
            ->search($filters['q'] ?? null)
            ->when($filters['category'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->when($filters['state'] ?? null, fn ($q, $state) => match ($state) {
                'published' => $q->published(),
                'scheduled' => $q->where('status', PostStatus::Scheduled)->where('published_at', '>', now()),
                'draft' => $q->where(fn ($q) => $q->where('status', PostStatus::Draft)->orWhereNull('published_at')),
            })
            ->orderByRaw('published_at is null desc')
            ->latestFirst()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Post $post) => [
                'id' => $post->id,
                'title' => $post->title,
                'path' => $post->publicPath(),
                'state' => $post->state(),
                'published_at' => SiteTime::local($post->published_at)?->format('M j, Y g:i A'),
                'category' => $post->category?->name,
                'author' => $post->author?->name,
                'is_featured' => $post->is_featured,
                'reading_minutes' => $post->reading_minutes,
                'thumb' => $post->featuredImage?->url(480),
                'noindex' => ! $post->seoIndexable(),
                'seo_missing' => $post->seoUsesFallbacks(),
            ]);

        return Inertia::render('posts/Index', [
            'posts' => $posts,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'state' => $filters['state'] ?? '',
                'category' => isset($filters['category']) ? (string) $filters['category'] : '',
            ],
            'categories' => Category::query()->ordered()->get(['id', 'name']),
            'counts' => [
                'all' => Post::query()->count(),
                'published' => Post::query()->published()->count(),
                'scheduled' => Post::query()->where('status', PostStatus::Scheduled)->where('published_at', '>', now())->count(),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('posts/Form', $this->formProps(null));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $post = DB::transaction(function () use ($data) {
            $post = Post::query()->create($this->attributes($data));
            $this->saveRelations($post, $data);

            return $post;
        });

        $this->flashSaved($post, created: true);

        return to_route('admin.posts.edit', $post);
    }

    public function edit(Post $post): Response
    {
        return Inertia::render('posts/Form', $this->formProps($post->load(['seo', 'tags'])));
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $data = $this->validated($request, $post);

        DB::transaction(function () use ($post, $data) {
            $post->update($this->attributes($data, $post));
            $this->saveRelations($post, $data);
        });

        $this->flashSaved($post->refresh());

        return to_route('admin.posts.edit', $post);
    }

    public function destroy(Post $post): RedirectResponse
    {
        $post->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "“{$post->title}” deleted. It is no longer on the website."]);

        return to_route('admin.posts.index');
    }

    public function toggleFeatured(Post $post): RedirectResponse
    {
        $post->update(['is_featured' => ! $post->is_featured]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(?Post $post): array
    {
        return [
            'post' => $post ? [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'category_id' => $post->category_id,
                'author_id' => $post->author_id,
                'excerpt' => $post->excerpt,
                'content' => $post->content,
                'featured_media_id' => $post->featured_media_id,
                'status' => $post->status === PostStatus::Draft ? 'draft' : 'published',
                'state' => $post->state(),
                'published_at' => SiteTime::toInput($post->published_at),
                'is_featured' => $post->is_featured,
                'tags' => $post->tags->pluck('name')->all(),
                'reading_minutes' => $post->reading_minutes,
                'path' => $post->publicPath(),
            ] : null,
            'categories' => Category::query()->ordered()->get(['id', 'name']),
            'authors' => Author::query()->orderBy('name')->get(['id', 'name']),
            'allTags' => Tag::query()->orderBy('name')->pluck('name'),
            'timezone' => SiteTime::ZONES[SiteTime::zone()],
            'now' => SiteTime::toInput(now()),
            'seo' => SeoForm::props($post, $post?->publicPath()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Post $post = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'slug' => [
                'required', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::notIn(Post::RESERVED_SLUGS),
                Rule::unique('posts', 'slug')->ignore($post),
            ],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'author_id' => ['nullable', 'integer', 'exists:authors,id'],
            'excerpt' => ['nullable', 'string', 'max:300'],
            'content' => ['nullable', 'string', 'max:500000'],
            'featured_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'published_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'is_featured' => ['boolean'],
            'tags' => ['array', 'max:10'],
            'tags.*' => ['string', 'max:40', 'distinct:ignore_case'],
            ...SeoForm::rules(),
        ], [
            'slug.regex' => 'Use lowercase letters, numbers and single hyphens only (e.g. moving-checklist).',
            'slug.not_in' => 'This URL is reserved for blog pages. Choose another.',
            'published_at.date_format' => 'Enter a valid date and time.',
        ], [
            ...SeoForm::attributes(),
            'slug' => 'URL',
            'category_id' => 'category',
            'author_id' => 'author',
            'featured_media_id' => 'featured image',
            'published_at' => 'publish date',
            'tags.*' => 'tag',
        ]);
    }

    /**
     * "Published" + a future date = scheduled. Publishing without a date uses now.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, ?Post $post = null): array
    {
        $publishedAt = SiteTime::fromInput($data['published_at'] ?? null);

        if ($data['status'] === 'published') {
            $publishedAt ??= now();
            $status = $publishedAt->isFuture() ? PostStatus::Scheduled : PostStatus::Published;
        } else {
            $status = PostStatus::Draft;
        }

        // Keep seconds when the minute did not change, so re-saving keeps the order of same-minute posts.
        if ($post?->published_at && $publishedAt && $post->published_at->format('Y-m-d H:i') === $publishedAt->format('Y-m-d H:i')) {
            $publishedAt = $post->published_at;
        }

        return [
            'title' => $data['title'],
            'slug' => $data['slug'],
            'category_id' => $data['category_id'],
            'author_id' => $data['author_id'] ?? null,
            'excerpt' => $data['excerpt'] ?? null,
            'content' => PostContent::sanitize($data['content'] ?? null),
            'featured_media_id' => $data['featured_media_id'] ?? null,
            'status' => $status,
            'published_at' => $publishedAt,
            'is_featured' => (bool) ($data['is_featured'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveRelations(Post $post, array $data): void
    {
        $post->tags()->sync(Tag::idsFor($data['tags'] ?? []));
        SeoForm::save($post, $data['seo'] ?? []);
    }

    private function flashSaved(Post $post, bool $created = false): void
    {
        $verb = $created ? 'created' : 'saved';

        $message = match ($post->state()) {
            'published' => "Post {$verb} and live on the website.",
            'scheduled' => "Post {$verb}. It goes live on ".SiteTime::local($post->published_at)->format('M j, Y \a\t g:i A').'.',
            default => "Draft {$verb}. It is not on the website yet.",
        };

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);
    }
}
