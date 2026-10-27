<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Attaching FAQs from the global FAQ bank to a page, service or area, in order.
 */
class FaqAttach
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(string $key = 'faq_ids'): array
    {
        return [
            $key => ['nullable', 'array', 'max:50'],
            "{$key}.*" => ['integer', 'distinct', 'exists:faqs,id'],
        ];
    }

    /**
     * @param  Model  $model  any model using HasFaqs
     * @param  array<int, int|string>|null  $ids
     */
    public static function sync(Model $model, ?array $ids): void
    {
        $model->faqs()->sync(
            collect($ids ?? [])->values()->mapWithKeys(fn ($id, int $i) => [(int) $id => ['sort' => $i]])->all(),
        );
    }

    /**
     * @param  Model  $model  any model using HasFaqs
     * @return list<int>
     */
    public static function ids(Model $model): array
    {
        return $model->faqs()->pluck('faqs.id')->map(fn ($id) => (int) $id)->all();
    }
}
