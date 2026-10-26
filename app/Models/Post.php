<?php

namespace App\Models;

use App\Enums\PostStatus;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\HasSlugRedirect;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'category_id', 'author_id', 'title', 'slug', 'excerpt', 'content',
    'featured_media_id', 'status', 'published_at', 'is_featured',
])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, HasSeo, HasSlugRedirect, SoftDeletes;

    public const WORDS_PER_MINUTE = 200;

    protected static function booted(): void
    {
        static::saving(function (Post $post) {
            if ($post->isDirty('content')) {
                $post->reading_minutes = static::readingMinutes((string) $post->content);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
            'reading_minutes' => 'integer',
        ];
    }

    public static function readingMinutes(string $html): int
    {
        $words = str_word_count(strip_tags($html));

        return max(1, (int) ceil($words / static::WORDS_PER_MINUTE));
    }

    /** Live on the site: published, or scheduled and its time has come. */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->whereIn('status', [PostStatus::Published, PostStatus::Scheduled])
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    #[Scope]
    protected function latestFirst(Builder $query): void
    {
        $query->orderByDesc('published_at')->orderByDesc('id');
    }

    public function isLive(): bool
    {
        return in_array($this->status, [PostStatus::Published, PostStatus::Scheduled], true)
            && $this->published_at !== null
            && $this->published_at->lte(now());
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'featured_media_id');
    }

    public function publicPath(): string
    {
        return $this->pathForSlug($this->slug);
    }

    public function pathForSlug(string $slug): string
    {
        return '/blog/'.$slug;
    }

    protected function seoFallbackDescription(): ?string
    {
        return $this->excerpt ?: $this->content;
    }

    protected function seoImageFallback(): ?Media
    {
        return $this->featuredImage;
    }

    public function seoOgType(): string
    {
        return 'article';
    }
}
