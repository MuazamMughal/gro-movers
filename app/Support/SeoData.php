<?php

namespace App\Support;

use App\Models\Media;

/**
 * Everything the <head> needs for one public page. Built by Seo::for().
 */
final class SeoData
{
    public function __construct(
        public readonly string $title,
        public readonly ?string $description,
        public readonly string $canonical,
        public readonly bool $index,
        public readonly bool $follow,
        public readonly string $ogType,
        public readonly string $ogTitle,
        public readonly ?string $ogDescription,
        public readonly ?Media $image,
        public readonly string $siteName,
    ) {}

    public function robots(): string
    {
        return ($this->index ? 'index' : 'noindex').','.($this->follow ? 'follow' : 'nofollow');
    }

    /** Absolute URL of the largest share image (JPG/PNG preferred for social networks). */
    public function imageUrl(): ?string
    {
        if ($this->image === null) {
            return null;
        }

        return Seo::absoluteUrl($this->image->url(1200, $this->image->fallbackFormat() ?? 'webp'));
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    public function imageSize(): array
    {
        if ($this->image === null) {
            return [null, null];
        }

        $format = $this->image->fallbackFormat() ?? 'webp';
        $variant = $this->image->variants->where('format', $format)->sortBy('width')
            ->first(fn ($v) => $v->width >= 1200) ?? $this->image->variants->where('format', $format)->sortBy('width')->last();

        return $variant ? [$variant->width, $variant->height] : $this->image->displaySize();
    }
}
