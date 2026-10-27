<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Sections\SectionAdmin;
use App\Support\FaqAttach;
use App\Support\SeoForm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Moving services (Local Moving, Packing…). Unlimited; one public page each at /services/{slug}.
 */
class ServiceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('services/Index', [
            'services' => Service::query()->ordered()->with(['seo', 'image.variants'])->withCount('areas')->get()
                ->map(fn (Service $s) => [
                    'id' => $s->id,
                    'title' => $s->title,
                    'path' => $s->publicPath(),
                    'is_active' => $s->is_active,
                    'show_in_menu' => $s->show_in_menu,
                    'show_on_home' => $s->show_on_home,
                    'areas_count' => $s->areas_count,
                    'thumb' => $s->image?->url(480),
                    'noindex' => ! $s->seoIndexable(),
                    'seo_missing' => $s->seoUsesFallbacks(),
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('services/Form', $this->formProps(null));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $service = DB::transaction(function () use ($request, $data) {
            $service = Service::query()->create([
                ...$this->attributes($data),
                'sort' => (int) Service::query()->max('sort') + 1,
            ]);
            $this->saveRelations($service, $request, $data);

            return $service;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "Service “{$service->title}” created."]);

        return to_route('admin.services.edit', $service);
    }

    public function edit(Service $service): Response
    {
        return Inertia::render('services/Form', $this->formProps($service->load('seo')));
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        $data = $this->validated($request, $service);

        DB::transaction(function () use ($request, $service, $data) {
            $service->update($this->attributes($data));
            $this->saveRelations($service, $request, $data);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Service saved.']);

        return to_route('admin.services.edit', $service);
    }

    public function destroy(Service $service): RedirectResponse
    {
        $service->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "“{$service->title}” deleted. Its page and city pages are no longer on the website."]);

        return to_route('admin.services.index');
    }

    /** Save the drag & drop order from the list. */
    public function reorder(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer', 'exists:services,id']])['ids'];

        DB::transaction(fn () => collect($ids)->each(fn (int $id, int $sort) => Service::query()->whereKey($id)->update(['sort' => $sort])));

        return back();
    }

    /** Quick on/off switches from the list (active, menu, home). */
    public function toggle(Request $request, Service $service): RedirectResponse
    {
        $field = $request->validate(['field' => ['required', Rule::in(['is_active', 'show_in_menu', 'show_on_home'])]])['field'];

        $service->update([$field => ! $service->{$field}]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(?Service $service): array
    {
        return [
            'service' => $service ? [
                'id' => $service->id,
                'title' => $service->title,
                'slug' => $service->slug,
                'short_description' => $service->short_description,
                'icon_media_id' => $service->icon_media_id,
                'image_media_id' => $service->image_media_id,
                'hero_media_id' => $service->hero_media_id,
                'is_active' => $service->is_active,
                'show_in_menu' => $service->show_in_menu,
                'show_on_home' => $service->show_on_home,
                'path' => $service->publicPath(),
                'faq_ids' => FaqAttach::ids($service),
            ] : null,
            'seo' => SeoForm::props($service, $service?->publicPath()),
            ...SectionAdmin::props($service),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Service $service = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('services', 'slug')->ignore($service)],
            'short_description' => ['nullable', 'string', 'max:500'],
            'icon_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'image_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'hero_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'is_active' => ['boolean'],
            'show_in_menu' => ['boolean'],
            'show_on_home' => ['boolean'],
            ...FaqAttach::rules(),
            ...SeoForm::rules(),
            ...SectionAdmin::rules($request),
        ], [
            'slug.regex' => 'Use lowercase letters, numbers and single hyphens only (e.g. local-moving).',
        ], [
            ...SeoForm::attributes(),
            ...SectionAdmin::attributes($request),
            'slug' => 'URL',
            'image_media_id' => 'card image',
            'hero_media_id' => 'hero image',
            'icon_media_id' => 'icon',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'title' => $data['title'],
            'slug' => $data['slug'],
            'short_description' => $data['short_description'] ?? null,
            'icon_media_id' => $data['icon_media_id'] ?? null,
            'image_media_id' => $data['image_media_id'] ?? null,
            'hero_media_id' => $data['hero_media_id'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'show_in_menu' => (bool) ($data['show_in_menu'] ?? true),
            'show_on_home' => (bool) ($data['show_on_home'] ?? true),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveRelations(Service $service, Request $request, array $data): void
    {
        SectionAdmin::save($service, $request);
        SeoForm::save($service, $data['seo'] ?? []);
        FaqAttach::sync($service, $data['faq_ids'] ?? []);
    }
}
