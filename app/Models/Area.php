<?php

namespace App\Models;

use App\Models\Concerns\HasFaqs;
use App\Models\Concerns\HasSections;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\HasSlugRedirect;
use Database\Factories\AreaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'city', 'state_name', 'state_code', 'slug', 'intro', 'hero_media_id', 'image_media_id',
    'lat', 'lng', 'zip_codes', 'map_embed', 'sort', 'is_active', 'show_in_menu',
])]
class Area extends Model
{
    /** @use HasFactory<AreaFactory> */
    use HasFactory, HasFaqs, HasSections, HasSeo, HasSlugRedirect, SoftDeletes;

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'zip_codes' => 'array',
            'is_active' => 'boolean',
            'show_in_menu' => 'boolean',
        ];
    }

    /** "Dallas, TX" */
    protected function name(): Attribute
    {
        return Attribute::get(fn () => "{$this->city}, {$this->state_code}");
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('city');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class);
    }

    public function neighbors(): BelongsToMany
    {
        return $this->belongsToMany(Area::class, 'area_neighbors', 'area_id', 'neighbor_id');
    }

    public function areaServicePages(): HasMany
    {
        return $this->hasMany(AreaServicePage::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function hero(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'hero_media_id');
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'image_media_id');
    }

    public function publicPath(): string
    {
        return $this->pathForSlug($this->slug);
    }

    public function pathForSlug(string $slug): string
    {
        return '/service-areas/'.$slug;
    }

    protected function seoFallbackTitle(): string
    {
        return "Movers in {$this->city}, {$this->state_code}";
    }

    protected function seoFallbackDescription(): ?string
    {
        return $this->intro;
    }

    protected function slugRedirectPairs(string $oldSlug, string $newSlug): array
    {
        $pairs = [[$this->pathForSlug($oldSlug), $this->pathForSlug($newSlug)]];

        foreach ($this->areaServicePages()->with('service')->get() as $page) {
            $pairs[] = [
                AreaServicePage::pathFor($oldSlug, $page->service->slug),
                AreaServicePage::pathFor($newSlug, $page->service->slug),
            ];
        }

        return $pairs;
    }
}
