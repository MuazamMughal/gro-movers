<?php

namespace App\Models;

use Database\Factories\SectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['type', 'data', 'media_ids', 'sort', 'is_visible'])]
class Section extends Model
{
    /** @use HasFactory<SectionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'media_ids' => 'array',
            'is_visible' => 'boolean',
        ];
    }

    public function sectionable(): MorphTo
    {
        return $this->morphTo();
    }
}
