<?php

namespace App\Models;

use App\Models\Concerns\HasFaqs;
use App\Models\Concerns\HasSections;
use App\Models\Concerns\HasSeo;
use Database\Factories\AreaServicePageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "{Service} in {City}" landing page. Uses the shared area×service template
 * unless use_template is false, in which case its own sections/SEO apply.
 */
#[Fillable(['area_id', 'service_id', 'is_active', 'use_template', 'title_override'])]
class AreaServicePage extends Model
{
    /** @use HasFactory<AreaServicePageFactory> */
    use HasFactory, HasFaqs, HasSections, HasSeo;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'use_template' => 'boolean',
        ];
    }

    public static function pathFor(string $areaSlug, string $serviceSlug): string
    {
        return "/service-areas/{$areaSlug}/{$serviceSlug}";
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function publicPath(): string
    {
        return static::pathFor($this->area->slug, $this->service->slug);
    }

    protected function seoFallbackTitle(): string
    {
        return $this->title_override
            ?: "{$this->service->title} in {$this->area->city}, {$this->area->state_code}";
    }
}
