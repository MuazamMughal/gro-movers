<?php

namespace App\Models\Concerns;

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

    protected function seoFallbackTitle(): string
    {
        return (string) ($this->title ?? '');
    }

    protected function seoFallbackDescription(): ?string
    {
        return null;
    }
}
