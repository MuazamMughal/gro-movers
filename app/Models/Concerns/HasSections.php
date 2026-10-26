<?php

namespace App\Models\Concerns;

use App\Models\Section;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Ordered page-builder sections (see config/sections.php).
 */
trait HasSections
{
    public static function bootHasSections(): void
    {
        static::deleted(function (self $model) {
            if (! method_exists($model, 'isForceDeleting') || $model->isForceDeleting()) {
                $model->sections()->delete();
            }
        });
    }

    public function sections(): MorphMany
    {
        return $this->morphMany(Section::class, 'sectionable')->orderBy('sort');
    }

    public function visibleSections(): MorphMany
    {
        return $this->sections()->where('is_visible', true);
    }
}
