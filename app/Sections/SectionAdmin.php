<?php

namespace App\Sections;

use App\Models\Area;
use App\Models\Faq;
use App\Models\Review;
use App\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * One-stop helpers for content forms that embed the section builder.
 *
 *   Inertia::render('pages/Form', [...SectionAdmin::props($page)]);
 *   $request->validate([...$own, ...SectionAdmin::rules($request)], [], SectionAdmin::attributes($request));
 *   SectionAdmin::save($page, $request);
 */
class SectionAdmin
{
    /**
     * @param  Model|null  $owner  any model using HasSections (null while creating)
     * @return array{sections: list<array<string, mixed>>, sectionSchema: array<string, mixed>, sectionOptions: array<string, mixed>}
     */
    public static function props(?Model $owner): array
    {
        return [
            'sections' => $owner ? app(SectionSync::class)->forAdmin($owner) : [],
            'sectionSchema' => app(SectionRegistry::class)->forAdmin(),
            'sectionOptions' => static::options(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(Request $request): array
    {
        return app(SectionRegistry::class)->rules((array) $request->input('sections', []));
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(Request $request): array
    {
        return app(SectionRegistry::class)->attributes((array) $request->input('sections', []));
    }

    public static function save(Model $owner, Request $request): void
    {
        app(SectionSync::class)->sync($owner, (array) $request->input('sections', []));
    }

    /**
     * Choices for the service / area / review / FAQ pickers.
     *
     * @return array<string, list<array{id: int, label: string, hint?: string|null}>>
     */
    public static function options(): array
    {
        return [
            'services' => Service::query()->ordered()->get(['id', 'title', 'is_active'])
                ->map(fn (Service $s) => ['id' => $s->id, 'label' => $s->title, 'hint' => $s->is_active ? null : 'inactive'])->all(),
            'areas' => Area::query()->ordered()->get(['id', 'city', 'state_code', 'is_active'])
                ->map(fn (Area $a) => ['id' => $a->id, 'label' => $a->name, 'hint' => $a->is_active ? null : 'inactive'])->all(),
            'reviews' => Review::query()->latest('reviewed_at')->get(['id', 'name', 'rating', 'body', 'is_active'])
                ->map(fn (Review $r) => ['id' => $r->id, 'label' => "{$r->name} ({$r->rating}★)", 'hint' => Str::limit($r->body, 60)])->all(),
            'faqs' => Faq::query()->orderBy('sort')->get(['id', 'question', 'is_active'])
                ->map(fn (Faq $f) => ['id' => $f->id, 'label' => $f->question, 'hint' => $f->is_active ? null : 'inactive'])->all(),
        ];
    }
}
