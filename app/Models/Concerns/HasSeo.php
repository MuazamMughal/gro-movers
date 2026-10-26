<?php

namespace App\Models\Concerns;

use App\Models\Media;
use App\Models\SeoMeta;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Str;

/**
 * Admin-editable SEO fields with sensible fallbacks when they are left empty.
 */
trait HasSeo
{
    public static function bootHasSeo(): void
    {
        static::deleted(function (self $model) {
            if (! method_exists($model, 'isForceDeleting') || $model->isForceDeleting()) {
                $model->seo()->delete();
            }
        });
    }

    public function seo(): MorphOne
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }

    public function seoTitle(): string
    {
        $custom = $this->seo?->meta_title;

        if (filled($custom)) {
            return $custom;
        }

        $suffix = (string) Settings::get('seo.title_suffix', '');

        return trim($this->seoFallbackTitle().' '.$suffix);
    }

    public function seoDescription(): ?string
    {
        $text = $this->seo?->meta_description;

        if (blank($text)) {
            $text = $this->seoFallbackDescription() ?? Settings::get('seo.default_description');
        }

        if (blank($text)) {
            return null;
        }

        return Str::limit(Str::squish(strip_tags($text)), 155, '…', preserveWords: true);
    }

    public function seoIndexable(): bool
    {
        return $this->seo?->robots_index ?? true;
    }

    public function seoFollowable(): bool
    {
        return $this->seo?->robots_follow ?? true;
    }

    /** True when the admin has set neither a custom title nor a description. */
    public function seoUsesFallbacks(): bool
    {
        return blank($this->seo?->meta_title) && blank($this->seo?->meta_description);
    }

    /**
     * Social share image: the custom OG image, else the record's own image
     * (hero, featured…), else the site default. Null when none is set.
     */
    public function seoImage(): ?Media
    {
        $id = $this->seo?->og_image_id
            ?? $this->seoImageFallback()?->id
            ?? Settings::get('seo.default_og_image_id');

        return $id ? Media::query()->with('variants')->find($id) : null;
    }

    /** og:type for this record. */
    public function seoOgType(): string
    {
        return 'website';
    }

    /**
     * What the admin SEO panel shows as placeholders when fields are left empty.
     *
     * @return array{title: string, description: string|null, image_id: int|null}
     */
    public function seoFallbacks(): array
    {
        $suffix = (string) Settings::get('seo.title_suffix', '');
        $description = $this->seoFallbackDescription() ?? Settings::get('seo.default_description');

        return [
            'title' => trim($this->seoFallbackTitle().' '.$suffix),
            'description' => blank($description) ? null : Str::limit(Str::squish(strip_tags($description)), 155, '…', preserveWords: true),
            'image_id' => $this->seoImageFallback()?->id ?? Settings::get('seo.default_og_image_id'),
        ];
    }

    protected function seoFallbackTitle(): string
    {
        return (string) ($this->title ?? '');
    }

    protected function seoFallbackDescription(): ?string
    {
        return null;
    }

    /** The record's own image used for social sharing when no OG image is set. */
    protected function seoImageFallback(): ?Media
    {
        return null;
    }
}
