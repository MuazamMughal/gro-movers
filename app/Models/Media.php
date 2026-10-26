<?php

namespace App\Models;

use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'uuid', 'disk', 'folder', 'filename', 'original_path', 'mime', 'size',
    'width', 'height', 'alt', 'title', 'caption', 'preset', 'crop',
])]
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'crop' => 'array',
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function variants(): HasMany
    {
        return $this->hasMany(MediaVariant::class)->orderBy('width');
    }

    public function isSvg(): bool
    {
        return $this->mime === 'image/svg+xml';
    }

    /**
     * Site-relative URL ("/storage/media/…"), so pre-built static HTML works on any host.
     */
    public static function pathUrl(string $disk, string $path): string
    {
        return parse_url(Storage::disk($disk)->url($path), PHP_URL_PATH) ?: '/'.$path;
    }

    public function originalUrl(): string
    {
        return static::pathUrl($this->disk, $this->original_path);
    }

    /**
     * URL of the smallest variant at least $width wide (largest available otherwise),
     * falling back to the original file when no variants exist (e.g. SVG).
     */
    public function url(?int $width = null, string $format = 'webp'): string
    {
        $variants = $this->variantsIn($format);

        if ($variants->isEmpty()) {
            return $this->originalUrl();
        }

        $variant = $width === null
            ? $variants->last()
            : ($variants->first(fn (MediaVariant $v) => $v->width >= $width) ?? $variants->last());

        return static::pathUrl($this->disk, $variant->path);
    }

    /** "…-480.webp 480w, …-768.webp 768w" */
    public function srcset(string $format = 'webp'): string
    {
        return $this->variantsIn($format)
            ->map(fn (MediaVariant $v) => static::pathUrl($this->disk, $v->path)." {$v->width}w")
            ->implode(', ');
    }

    /** jpg or png fallback format of this image's variants, or null if it has none. */
    public function fallbackFormat(): ?string
    {
        return $this->variants->firstWhere('format', '!=', 'webp')?->format;
    }

    /** Dimensions of the largest processed output (what the page actually shows). */
    public function displaySize(): array
    {
        $largest = $this->variants->sortBy('width')->last();

        return [$largest?->width ?? $this->width, $largest?->height ?? $this->height];
    }

    /**
     * @return Collection<int, MediaVariant>
     */
    private function variantsIn(string $format): Collection
    {
        return $this->variants->where('format', $format)->sortBy('width')->values();
    }

    /**
     * Shape used by the admin panel (library, picker, cropper).
     *
     * @return array<string, mixed>
     */
    public function toAdminArray(): array
    {
        [$displayWidth, $displayHeight] = $this->displaySize();

        return [
            'id' => $this->id,
            'filename' => $this->filename,
            'mime' => $this->mime,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'display_width' => $displayWidth,
            'display_height' => $displayHeight,
            'alt' => $this->alt,
            'title' => $this->title,
            'caption' => $this->caption,
            'preset' => $this->preset,
            'crop' => $this->crop,
            'is_svg' => $this->isSvg(),
            'url' => $this->url(),
            'thumb_url' => $this->url(480),
            'original_url' => $this->originalUrl(),
            'variants_count' => $this->variants->count(),
            'variants_size' => $this->variants->sum('size'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
