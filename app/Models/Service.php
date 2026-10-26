<?php

namespace App\Models;

use App\Models\Concerns\HasFaqs;
use App\Models\Concerns\HasSections;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\HasSlugRedirect;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'title', 'slug', 'short_description', 'icon_media_id', 'image_media_id',
    'hero_media_id', 'sort', 'is_active', 'show_in_menu', 'show_on_home',
])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, HasFaqs, HasSections, HasSeo, HasSlugRedirect, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'show_in_menu' => 'boolean',
            'show_on_home' => 'boolean',
        ];
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('title');
    }

    public function areas(): BelongsToMany
    {
        return $this->belongsToMany(Area::class);
    }

    public function areaServicePages(): HasMany
    {
        return $this->hasMany(AreaServicePage::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function icon(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'icon_media_id');
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'image_media_id');
    }

    public function hero(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'hero_media_id');
    }

    public function publicPath(): string
    {
        return $this->pathForSlug($this->slug);
    }

    public function pathForSlug(string $slug): string
    {
        return '/services/'.$slug;
    }

    protected function seoFallbackDescription(): ?string
    {
        return $this->short_description;
    }

    protected function slugRedirectPairs(string $oldSlug, string $newSlug): array
    {
        $pairs = [[$this->pathForSlug($oldSlug), $this->pathForSlug($newSlug)]];

        foreach ($this->areaServicePages()->with('area')->get() as $page) {
            $pairs[] = [
                AreaServicePage::pathFor($page->area->slug, $oldSlug),
                AreaServicePage::pathFor($page->area->slug, $newSlug),
            ];
        }

        return $pairs;
    }
}
