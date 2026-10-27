<?php

namespace App\Models;

use App\Enums\ReviewSource;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'name', 'city', 'rating', 'body', 'source', 'reviewed_at', 'photo_media_id',
    'area_id', 'service_id', 'is_featured', 'is_active', 'is_sample', 'sort',
])]
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'source' => ReviewSource::class,
            'reviewed_at' => 'date',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'is_sample' => 'boolean',
        ];
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    #[Scope]
    protected function featured(Builder $query): void
    {
        $query->where('is_featured', true);
    }

    /** Featured first, then the admin's order, then newest. */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderByDesc('is_featured')->orderBy('sort')->orderByDesc('reviewed_at')->orderByDesc('id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'photo_media_id');
    }
}
