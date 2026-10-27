<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SystemPage;
use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\AreaServicePage;
use App\Models\Page;
use App\Models\Service;
use App\Sections\SectionAdmin;
use App\Sections\SectionSync;
use App\Services\TokenReplacer;
use App\Support\FaqAttach;
use App\Support\SeoForm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "{Service} in {City}" pages: the grid of all combinations, the shared
 * template (sections + SEO with tokens) and per-page custom content.
 */
class AreaServiceController extends Controller
{
    public function index(): Response
    {
        $pages = AreaServicePage::query()->get()->keyBy(fn (AreaServicePage $p) => "{$p->area_id}-{$p->service_id}");
        $offered = DB::table('area_service')->get()->map(fn ($r) => "{$r->area_id}-{$r->service_id}")->flip();
        $services = Service::query()->ordered()->get(['id', 'title', 'slug', 'is_active']);

        return Inertia::render('area-services/Index', [
            'services' => $services->map(fn (Service $s) => ['id' => $s->id, 'title' => $s->title, 'is_active' => $s->is_active]),
            'areas' => Area::query()->ordered()->get(['id', 'city', 'state_code', 'slug', 'is_active'])
                ->map(fn (Area $a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'is_active' => $a->is_active,
                    'cells' => $services->map(function (Service $s) use ($a, $pages, $offered) {
                        $page = $pages->get("{$a->id}-{$s->id}");

                        return [
                            'service_id' => $s->id,
                            'offered' => isset($offered["{$a->id}-{$s->id}"]),
                            'page_id' => $page?->id,
                            'is_active' => (bool) $page?->is_active,
                            'custom' => $page && ! $page->use_template,
                            'path' => AreaServicePage::pathFor($a->slug, $s->slug),
                        ];
                    }),
                ]),
            'stats' => [
                'live' => AreaServicePage::query()->active()
                    ->whereHas('area', fn ($q) => $q->where('is_active', true))
                    ->whereHas('service', fn ($q) => $q->where('is_active', true))
                    ->count(),
                'custom' => AreaServicePage::query()->where('use_template', false)->count(),
            ],
        ]);
    }

    public function toggle(AreaServicePage $areaService): RedirectResponse
    {
        $areaService->update(['is_active' => ! $areaService->is_active]);

        return back();
    }

    public function edit(AreaServicePage $areaService): Response
    {
        $areaService->load(['area', 'service', 'seo']);
        $template = $this->template();

        return Inertia::render('area-services/Edit', [
            'page' => [
                'id' => $areaService->id,
                'heading' => $areaService->heading(),
                'title_override' => $areaService->title_override,
                'use_template' => $areaService->use_template,
                'is_active' => $areaService->is_active,
                'path' => $areaService->publicPath(),
                'area' => ['id' => $areaService->area->id, 'name' => $areaService->area->name],
                'service' => ['id' => $areaService->service->id, 'title' => $areaService->service->title],
                'faq_ids' => FaqAttach::ids($areaService),
            ],
            // So "Customize" can start from the template instead of an empty page.
            'templateSections' => app(SectionSync::class)->forAdmin($template),
            'tokens' => (new TokenReplacer($areaService->area, $areaService->service))->tokens(),
            'seo' => SeoForm::props($areaService, $areaService->publicPath()),
            ...SectionAdmin::props($areaService),
        ]);
    }

    public function update(Request $request, AreaServicePage $areaService): RedirectResponse
    {
        $data = $request->validate([
            'title_override' => ['nullable', 'string', 'max:120'],
            'use_template' => ['boolean'],
            'is_active' => ['boolean'],
            ...FaqAttach::rules(),
            ...SeoForm::rules(),
            ...SectionAdmin::rules($request),
        ], [], [...SeoForm::attributes(), ...SectionAdmin::attributes($request), 'title_override' => 'page title']);

        DB::transaction(function () use ($request, $areaService, $data) {
            $areaService->update([
                'title_override' => $data['title_override'] ?? null,
                'use_template' => (bool) ($data['use_template'] ?? true),
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            // Custom content is kept even while the template is in use, so switching back is lossless.
            SectionAdmin::save($areaService, $request);
            SeoForm::save($areaService, $data['seo'] ?? []);
            FaqAttach::sync($areaService, $data['faq_ids'] ?? []);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Page saved.']);

        return to_route('admin.area-services.edit', $areaService);
    }

    public function editTemplate(): Response
    {
        $template = $this->template()->load('seo');
        $example = AreaServicePage::query()->with(['area', 'service'])->first();

        return Inertia::render('area-services/Template', [
            'faq_ids' => FaqAttach::ids($template),
            'seo' => SeoForm::props($template, $example?->publicPath() ?? '/service-areas/dallas-tx/local-moving'),
            'tokens' => (new TokenReplacer($example?->area, $example?->service))->tokens(),
            'usage' => AreaServicePage::query()->where('use_template', true)->count(),
            ...SectionAdmin::props($template),
        ]);
    }

    public function updateTemplate(Request $request): RedirectResponse
    {
        $template = $this->template();

        $data = $request->validate([
            ...FaqAttach::rules(),
            ...SeoForm::rules(),
            ...SectionAdmin::rules($request),
        ], [], [...SeoForm::attributes(), ...SectionAdmin::attributes($request)]);

        DB::transaction(function () use ($request, $template, $data) {
            SectionAdmin::save($template, $request);
            SeoForm::save($template, $data['seo'] ?? []);
            FaqAttach::sync($template, $data['faq_ids'] ?? []);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Template saved. Every page using it is updated.']);

        return to_route('admin.area-services.template');
    }

    private function template(): Page
    {
        return Page::system(SystemPage::AreaServiceTemplate)
            ?? abort(500, 'The area × service template page is missing. Run the database seeders.');
    }
}
