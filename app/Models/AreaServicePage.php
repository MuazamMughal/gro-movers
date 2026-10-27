<?php

namespace App\Models;

use App\Enums\SystemPage;
use App\Models\Concerns\HasFaqs;
use App\Models\Concerns\HasSections;
use App\Models\Concerns\HasSeo;
use App\Services\TokenReplacer;
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

    /** The H1 and fallback title: the custom title, or "{Service} in {City}, {ST}". */
    public function heading(): string
    {
        return $this->title_override
            ?: "{$this->service->title} in {$this->area->city}, {$this->area->state_code}";
    }

    public function template(): ?Page
    {
        return Page::system(SystemPage::AreaServiceTemplate);
    }

    /**
     * The record whose sections and FAQs the page shows: the shared template,
     * or this page itself when it has its own content.
     */
    public function contentOwner(): Model
    {
        return $this->use_template ? ($this->template() ?? $this) : $this;
    }

    /**
     * Pages using the template inherit its SEO with tokens filled in. The template's
     * canonical URL is never copied: every city page is its own canonical.
     */
    public function resolveSeo(): void
    {
        if (! $this->use_template || ! ($templateSeo = $this->template()?->seo)) {
            $this->loadMissing('seo');

            return;
        }

        $tokens = new TokenReplacer($this->area, $this->service);
        $seo = new SeoMeta;

        foreach (['meta_title', 'meta_description', 'og_title', 'og_description'] as $field) {
            $seo->{$field} = $tokens->replace($templateSeo->{$field});
        }

        $seo->og_image_id = $templateSeo->og_image_id;
        $seo->robots_index = $templateSeo->robots_index;
        $seo->robots_follow = $templateSeo->robots_follow;

        $this->setRelation('seo', $seo);
    }

    protected function seoFallbackTitle(): string
    {
        return $this->heading();
    }

    protected function seoFallbackDescription(): ?string
    {
        return $this->area->intro ?? $this->service->short_description;
    }

    protected function seoImageFallback(): ?Media
    {
        return $this->area->hero ?? $this->service->hero ?? $this->area->image ?? $this->service->image;
    }
}
