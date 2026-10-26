<?php

namespace App\Models;

use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
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

    public function originalUrl(): string
    {
        return Storage::disk($this->disk)->url($this->original_path);
    }

    /**
     * URL of the smallest variant at least $width wide (largest available otherwise),
     * falling back to the original file when no variants exist.
     */
    public function url(?int $width = null, string $format = 'webp'): string
    {
        $variants = $this->variants->where('format', $format);

        if ($variants->isEmpty()) {
            return $this->originalUrl();
        }

        $variant = $width === null
            ? $variants->last()
            : ($variants->first(fn (MediaVariant $v) => $v->width >= $width) ?? $variants->last());

        return $variant->url();
    }
}
