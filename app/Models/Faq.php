<?php

namespace App\Models;

use Database\Factories\FaqFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

#[Fillable(['question', 'answer', 'sort', 'is_active'])]
class Faq extends Model
{
    /** @use HasFactory<FaqFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('id');
    }

    /**
     * Where this FAQ is shown: records it is attached to, and FAQ sections that
     * picked it by hand.
     *
     * @return list<array{label: string, path: string|null}>
     */
    public function usages(): array
    {
        $usages = [];

        foreach (DB::table('faqables')->where('faq_id', $this->id)->get() as $row) {
            $model = Relation::getMorphedModel($row->faqable_type);
            $record = $model ? $model::query()->find($row->faqable_id) : null;

            if ($record) {
                $usages[] = ['label' => static::describe($record), 'path' => $record->publicPath()];
            }
        }

        $sections = Section::query()->where('type', 'faq')->with('sectionable')->get()
            ->filter(fn (Section $s) => in_array($this->id, array_map('intval', $s->data['faqs'] ?? []), true));

        foreach ($sections as $section) {
            if ($section->sectionable) {
                $usages[] = ['label' => static::describe($section->sectionable).' (FAQ section)', 'path' => $section->sectionable->publicPath()];
            }
        }

        return $usages;
    }

    private static function describe(Model $record): string
    {
        $name = match (true) {
            $record instanceof AreaServicePage => $record->heading(),
            $record instanceof Area => $record->name,
            default => $record->title ?? "#{$record->getKey()}",
        };

        return class_basename($record) === 'AreaServicePage' ? "City service page: {$name}" : class_basename($record).": {$name}";
    }

    public function pages(): MorphToMany
    {
        return $this->morphedByMany(Page::class, 'faqable');
    }

    public function services(): MorphToMany
    {
        return $this->morphedByMany(Service::class, 'faqable');
    }

    public function areas(): MorphToMany
    {
        return $this->morphedByMany(Area::class, 'faqable');
    }
}
