<?php

namespace App\Models\Concerns;

use App\Models\Faq;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * FAQs attached from the global FAQ bank, in their per-record order.
 */
trait HasFaqs
{
    public static function bootHasFaqs(): void
    {
        static::deleted(function (self $model) {
            if (! method_exists($model, 'isForceDeleting') || $model->isForceDeleting()) {
                $model->faqs()->detach();
            }
        });
    }

    public function faqs(): MorphToMany
    {
        return $this->morphToMany(Faq::class, 'faqable')
            ->withPivot('sort')
            ->orderByPivot('sort');
    }
}
