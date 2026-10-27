<?php

namespace App\Sections;

use App\Models\Section;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Saves the builder's section list onto a page/service/area/…: updates known
 * sections, creates new ones, deletes removed ones and stores the order.
 *
 *   $request->validate($registry->rules($request->input('sections', [])));
 *   app(SectionSync::class)->sync($page, $request->input('sections', []));
 */
class SectionSync
{
    public function __construct(private readonly SectionRegistry $registry) {}

    /**
     * @param  Model  $owner  any model using HasSections
     * @param  array<int, array{id?: int|null, type: string, data?: array<string, mixed>, is_visible?: bool}>  $sections
     */
    public function sync(Model $owner, array $sections): void
    {
        DB::transaction(function () use ($owner, $sections) {
            $existing = $owner->sections()->get()->keyBy('id');
            $keep = [];

            foreach (array_values($sections) as $sort => $item) {
                $data = $this->registry->sanitize($item['type'], $item['data'] ?? []);

                $attributes = [
                    'type' => $item['type'],
                    'data' => $data,
                    'media_ids' => $this->registry->mediaIds($item['type'], $data),
                    'sort' => $sort,
                    'is_visible' => (bool) ($item['is_visible'] ?? true),
                ];

                // Only reuse IDs that belong to this owner; anything else becomes a new section.
                $section = isset($item['id']) ? $existing->get((int) $item['id']) : null;

                if ($section instanceof Section) {
                    $section->update($attributes);
                } else {
                    $section = $owner->sections()->create($attributes);
                }

                $keep[] = $section->id;
            }

            $owner->sections()->whereNotIn('id', $keep)->delete();
        });

        $owner->unsetRelation('sections');
        $owner->unsetRelation('visibleSections');
    }

    /**
     * Sections in the shape the Vue builder edits.
     *
     * @param  Model  $owner  any model using HasSections
     * @return list<array<string, mixed>>
     */
    public function forAdmin(Model $owner): array
    {
        return $owner->sections()->get()
            ->map(fn (Section $s) => [
                'id' => $s->id,
                'type' => $s->type,
                // Merge defaults so fields added to a type later show up in old sections.
                'data' => array_replace($this->registry->exists($s->type) ? $this->registry->defaults($s->type) : [], $s->data ?? []),
                'is_visible' => $s->is_visible,
            ])
            ->values()
            ->all();
    }
}
