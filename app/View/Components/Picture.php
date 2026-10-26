<?php

namespace App\View\Components;

use App\Models\Media;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Responsive image: <picture> with a WebP srcset and a JPG/PNG fallback,
 * explicit width/height (no layout shift) and lazy loading unless eager.
 *
 *   <x-picture :media="$service->hero" sizes="100vw" eager />
 *   <x-picture :media="$mediaId" sizes="(min-width: 768px) 33vw, 100vw" class="rounded-2xl" />
 */
class Picture extends Component
{
    /** @var array<int, Media|null> per-request cache for ID lookups */
    private static array $resolved = [];

    public ?Media $image;

    public function __construct(
        Media|int|string|null $media,
        public string $sizes = '100vw',
        public bool $eager = false,
        public ?string $alt = null,
        public ?string $imgClass = null,
    ) {
        $this->image = $this->findMedia($media);
    }

    public function shouldRender(): bool
    {
        return $this->image !== null;
    }

    public function render(): View
    {
        [$width, $height] = $this->image->displaySize();

        return view('components.picture', [
            'webpSrcset' => $this->image->isSvg() ? '' : $this->image->srcset('webp'),
            'fallbackSrcset' => ($format = $this->image->fallbackFormat()) ? $this->image->srcset($format) : '',
            'src' => $this->image->url(null, $this->image->fallbackFormat() ?? 'webp'),
            'width' => $width,
            'height' => $height,
            'altText' => $this->alt ?? (string) $this->image->alt,
        ]);
    }

    private function findMedia(Media|int|string|null $media): ?Media
    {
        if ($media instanceof Media) {
            return $media->loadMissing('variants');
        }

        if (blank($media) || ! is_numeric($media)) {
            return null;
        }

        $id = (int) $media;

        return static::$resolved[$id] ??= Media::query()->with('variants')->find($id);
    }

    /** Clear the per-request cache (between static builds and tests). */
    public static function flushCache(): void
    {
        static::$resolved = [];
    }
}
