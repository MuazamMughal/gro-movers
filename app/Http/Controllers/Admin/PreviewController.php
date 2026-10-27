<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Section;
use App\Models\SeoMeta;
use App\Sections\SectionRegistry;
use App\Sections\SectionRenderer;
use App\Support\Seo;
use App\Support\SeoForm;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Renders what a page will look like from the editor's current, unsaved state.
 * The editor posts its form as JSON into a new browser tab; nothing is saved.
 */
class PreviewController extends Controller
{
    public function __construct(
        private readonly SectionRegistry $registry,
        private readonly SectionRenderer $renderer,
    ) {}

    public function page(Request $request): View
    {
        $payload = json_decode((string) $request->input('payload'), true);
        abort_unless(is_array($payload), 422, 'Invalid preview data.');

        $sections = array_values(array_filter((array) ($payload['sections'] ?? []), 'is_array'));

        try {
            validator(
                ['sections' => $sections, 'seo' => $payload['seo'] ?? []],
                [...$this->registry->rules($sections), ...SeoForm::rules()],
                [],
                $this->registry->attributes($sections),
            )->validate();
        } catch (ValidationException $e) {
            return view('admin.preview-error', ['errors' => $e->validator->errors()->all()]);
        }

        // Work on an unsaved copy so FAQ relations etc. still resolve for existing pages.
        $page = isset($payload['id']) ? Page::query()->findOrFail((int) $payload['id']) : new Page;
        $page->forceFill([
            'title' => (string) ($payload['title'] ?? 'Untitled page'),
            'slug' => (string) ($payload['slug'] ?? $page->slug ?? 'preview'),
        ]);
        $page->setRelation('seo', new SeoMeta(Arr::only($payload['seo'] ?? [], SeoForm::FIELDS)));

        $models = collect($sections)->map(fn (array $item, int $i) => (new Section)->forceFill([
            'id' => $item['id'] ?? 900000 + $i,
            'type' => $item['type'],
            'data' => $this->registry->sanitize($item['type'], $item['data'] ?? []),
            'is_visible' => (bool) ($item['is_visible'] ?? true),
            'sort' => $i,
        ]));

        return view('pages.show', [
            'page' => $page,
            'seo' => Seo::for($page, $page->publicPath() ?? '/'),
            'rendered' => $this->renderer->render($page, sections: $models),
            'preview' => true,
        ]);
    }
}
