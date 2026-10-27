<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Service;
use App\Sections\SectionAdmin;
use App\Services\AreaServiceSync;
use App\Support\FaqAttach;
use App\Support\SeoForm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Service areas (cities). Each has a page at /service-areas/{slug} and one
 * "{service} in {city}" page per offered service.
 */
class AreaController extends Controller
{
    public function __construct(private readonly AreaServiceSync $sync) {}

    public function index(): Response
    {
        return Inertia::render('areas/Index', [
            'areas' => Area::query()->ordered()->with('seo')->withCount('services')->get()
                ->map(fn (Area $a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'state_name' => $a->state_name,
                    'path' => $a->publicPath(),
                    'is_active' => $a->is_active,
                    'show_in_menu' => $a->show_in_menu,
                    'services_count' => $a->services_count,
                    'noindex' => ! $a->seoIndexable(),
                    'seo_missing' => $a->seoUsesFallbacks(),
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('areas/Form', $this->formProps(null));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $area = DB::transaction(function () use ($request, $data) {
            $area = Area::query()->create([...$this->attributes($data), 'sort' => (int) Area::query()->max('sort') + 1]);
            $this->saveRelations($area, $request, $data, []);

            return $area;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$area->name} created with ".count($data['service_ids'] ?? []).' city service pages.']);

        return to_route('admin.areas.edit', $area);
    }

    public function edit(Area $area): Response
    {
        return Inertia::render('areas/Form', $this->formProps($area->load('seo')));
    }

    public function update(Request $request, Area $area): RedirectResponse
    {
        $data = $this->validated($request, $area);
        $previous = $area->services()->pluck('services.id')->map(fn ($id) => (int) $id)->all();

        DB::transaction(function () use ($request, $area, $data, $previous) {
            $area->update($this->attributes($data));
            $this->saveRelations($area, $request, $data, $previous);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Service area saved.']);

        return to_route('admin.areas.edit', $area);
    }

    public function destroy(Area $area): RedirectResponse
    {
        $area->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$area->name} deleted. Its pages are no longer on the website."]);

        return to_route('admin.areas.index');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer', 'exists:areas,id']])['ids'];

        DB::transaction(fn () => collect($ids)->each(fn (int $id, int $sort) => Area::query()->whereKey($id)->update(['sort' => $sort])));

        return back();
    }

    public function toggle(Request $request, Area $area): RedirectResponse
    {
        $field = $request->validate(['field' => ['required', Rule::in(['is_active', 'show_in_menu'])]])['field'];

        $area->update([$field => ! $area->{$field}]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(?Area $area): array
    {
        return [
            'area' => $area ? [
                'id' => $area->id,
                'city' => $area->city,
                'state_name' => $area->state_name,
                'state_code' => $area->state_code,
                'slug' => $area->slug,
                'intro' => $area->intro,
                'hero_media_id' => $area->hero_media_id,
                'image_media_id' => $area->image_media_id,
                'lat' => $area->lat,
                'lng' => $area->lng,
                'zip_codes' => $area->zip_codes ?? [],
                'map_embed' => $area->map_embed,
                'is_active' => $area->is_active,
                'show_in_menu' => $area->show_in_menu,
                'service_ids' => $area->services()->pluck('services.id'),
                'neighbor_ids' => $area->neighbors()->pluck('areas.id'),
                'faq_ids' => FaqAttach::ids($area),
                'path' => $area->publicPath(),
            ] : null,
            'serviceOptions' => Service::query()->ordered()->get(['id', 'title', 'is_active'])
                ->map(fn (Service $s) => ['id' => $s->id, 'label' => $s->title, 'hint' => $s->is_active ? null : 'inactive']),
            'areaOptions' => Area::query()->ordered()->when($area, fn ($q) => $q->whereKeyNot($area->id))->get(['id', 'city', 'state_code'])
                ->map(fn (Area $a) => ['id' => $a->id, 'label' => $a->name]),
            'seo' => SeoForm::props($area, $area?->publicPath()),
            ...SectionAdmin::props($area),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Area $area = null): array
    {
        $request->merge(['state_code' => strtoupper((string) $request->input('state_code'))]);

        return $request->validate([
            'city' => ['required', 'string', 'max:80'],
            'state_name' => ['required', 'string', 'max:60'],
            'state_code' => ['required', 'alpha', 'size:2'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('areas', 'slug')->ignore($area)],
            'intro' => ['nullable', 'string', 'max:1000'],
            'hero_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'image_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'zip_codes' => ['nullable', 'array', 'max:200'],
            'zip_codes.*' => ['regex:/^\d{5}$/', 'distinct'],
            'map_embed' => ['nullable', 'string', 'max:2000', 'regex:#^https://(www\.)?google\.[a-z.]+/maps/embed\?#i'],
            'is_active' => ['boolean'],
            'show_in_menu' => ['boolean'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', 'distinct', 'exists:services,id'],
            'neighbor_ids' => ['nullable', 'array', 'max:20'],
            'neighbor_ids.*' => ['integer', 'distinct', 'exists:areas,id', Rule::notIn([$area?->id ?? 0])],
            ...FaqAttach::rules(),
            ...SeoForm::rules(),
            ...SectionAdmin::rules($request),
        ], [
            'slug.regex' => 'Use lowercase letters, numbers and single hyphens only (e.g. plano-tx).',
            'zip_codes.*.regex' => 'ZIP codes must be 5 digits.',
            'map_embed.regex' => 'Paste the src link from Google Maps → Share → Embed a map (starts with https://www.google.com/maps/embed?).',
        ], [
            ...SeoForm::attributes(),
            ...SectionAdmin::attributes($request),
            'slug' => 'URL',
            'state_code' => 'state code',
            'state_name' => 'state',
            'map_embed' => 'map embed link',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'city' => $data['city'],
            'state_name' => $data['state_name'],
            'state_code' => $data['state_code'],
            'slug' => $data['slug'],
            'intro' => $data['intro'] ?? null,
            'hero_media_id' => $data['hero_media_id'] ?? null,
            'image_media_id' => $data['image_media_id'] ?? null,
            'lat' => $data['lat'] ?? null,
            'lng' => $data['lng'] ?? null,
            'zip_codes' => array_values($data['zip_codes'] ?? []),
            'map_embed' => $data['map_embed'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'show_in_menu' => (bool) ($data['show_in_menu'] ?? true),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $previousServices
     */
    private function saveRelations(Area $area, Request $request, array $data, array $previousServices): void
    {
        $area->services()->sync($data['service_ids'] ?? []);
        $area->neighbors()->sync($data['neighbor_ids'] ?? []);
        $this->sync->forArea($area, $previousServices);

        SectionAdmin::save($area, $request);
        SeoForm::save($area, $data['seo'] ?? []);
        FaqAttach::sync($area, $data['faq_ids'] ?? []);
    }
}
