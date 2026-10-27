<?php

namespace App\Http\Controllers\Site;

use App\Enums\SystemPage;
use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\AreaServicePage;
use App\Models\Page;
use App\Models\Review;
use App\Models\Service;
use App\Sections\AutoSections;
use App\Sections\RenderedSections;
use App\Sections\SectionRenderer;
use App\Seo\Schema;
use App\Support\Breadcrumbs;
use App\Support\Seo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

class AreaController extends Controller
{
    public function __construct(private readonly SectionRenderer $renderer) {}

    /** /service-areas: the page's sections plus every active city, grouped by state. */
    public function index(): View
    {
        $page = Page::system(SystemPage::AreasIndex);
        abort_unless($page && $page->isPublished(), 404);

        $own = $page->visibleSections()->get();
        $sections = AutoSections::complete($own, ['heading' => $page->title, 'theme' => 'dark'], withFaq: $page->faqs()->active()->exists());
        $closing = $sections->last()?->type === 'cta_banner' ? collect([$sections->pop()]) : collect();

        return view('areas.index', [
            'page' => $page,
            'seo' => Seo::for($page, '/service-areas'),
            'breadcrumbs' => Breadcrumbs::areas(),
            'rendered' => $this->renderer->render($page, sections: $sections),
            'closing' => $this->renderer->render($page, sections: $closing, allowH1: false),
            // The page's own areas grid replaces the automatic list.
            'states' => $own->contains('type', 'areas_grid') ? collect() : Area::query()->active()->ordered()->with('image.variants')->get()
                ->groupBy(fn (Area $a) => $a->state_name ?: $a->state_code)
                ->sortKeys(),
        ]);
    }

    /** /service-areas/{area} */
    public function show(string $area): View
    {
        $area = Area::query()->active()->where('slug', $area)->with(['seo', 'neighbors' => fn ($q) => $q->active()->ordered()])->firstOrFail();

        $sections = AutoSections::complete(
            $area->visibleSections()->get(),
            [
                'eyebrow' => "Serving {$area->city} and nearby",
                'heading' => "Movers in {$area->city}, {$area->state_code}",
                'subheading' => $area->intro,
                'background_image' => $area->hero_media_id,
                'primary_button' => ['label' => 'Get a Free Quote', 'url' => '/get-a-quote'],
                'theme' => 'dark',
            ],
            withFaq: $area->faqs()->active()->exists(),
        );
        $closing = $sections->last()?->type === 'cta_banner' ? collect([$sections->pop()]) : collect();

        return view('areas.show', [
            'area' => $area,
            'seo' => Seo::for($area, $area->publicPath()),
            'breadcrumbs' => Breadcrumbs::area($area),
            'schema' => [Schema::area($area)],
            'rendered' => $this->renderer->render($area, area: $area, sections: $sections),
            'closing' => $this->renderer->render($area, area: $area, sections: $closing, allowH1: false),
            'services' => $this->liveServices($area),
            'reviews' => $this->reviewsFor($area, $sections),
            'neighbors' => $area->neighbors,
        ]);
    }

    /** /service-areas/{area}/{service}: "{Service} in {City}" */
    public function service(string $area, string $service): View
    {
        $area = Area::query()->active()->where('slug', $area)->firstOrFail();
        $service = Service::query()->active()->where('slug', $service)->firstOrFail();

        $page = AreaServicePage::query()
            ->active()
            ->where('area_id', $area->id)
            ->where('service_id', $service->id)
            ->whereExists(fn ($q) => $q->from('area_service')->whereColumn('area_service.area_id', 'area_service_pages.area_id')->whereColumn('area_service.service_id', 'area_service_pages.service_id'))
            ->firstOrFail()
            ->setRelation('area', $area)
            ->setRelation('service', $service);

        $owner = $page->contentOwner();
        $page->resolveSeo();

        $sections = AutoSections::complete(
            $owner->visibleSections()->get(),
            [
                'eyebrow' => "{$service->title} · {$area->city}, {$area->state_code}",
                'heading' => $page->heading(),
                'subheading' => $service->short_description,
                'background_image' => $area->hero_media_id ?? $service->hero_media_id,
                'primary_button' => ['label' => 'Get a Free Quote', 'url' => '/get-a-quote'],
                'theme' => 'dark',
            ],
            withFaq: $owner->faqs()->active()->exists(),
        );
        $closing = $sections->last()?->type === 'cta_banner' ? collect([$sections->pop()]) : collect();

        return view('areas.service', [
            'area' => $area,
            'service' => $service,
            'page' => $page,
            'seo' => Seo::for($page, $page->publicPath()),
            'breadcrumbs' => Breadcrumbs::areaService($area, $service),
            'schema' => [Schema::areaService($page)],
            'rendered' => $this->renderer->render($owner, area: $area, service: $service, sections: $sections),
            'closing' => $this->renderer->render($owner, area: $area, service: $service, sections: $closing, allowH1: false),
            'siblings' => $this->liveServices($area)->reject(fn ($s) => $s['id'] === $service->id)->values(),
        ]);
    }

    /**
     * Reviews block for a city page, unless the page already has a reviews section.
     * Shows the city's own reviews, else featured reviews under a general heading.
     */
    private function reviewsFor(Area $area, Collection $sections): ?RenderedSections
    {
        if ($sections->contains('type', 'reviews_slider')) {
            return null;
        }

        $local = Review::query()->active()->where('area_id', $area->id)->exists();
        $section = AutoSections::make('reviews_slider', [
            'mode' => 'context',
            'heading' => $local ? "What {$area->city} customers say" : 'What our customers say',
            'limit' => 6,
            'show_summary' => $local,
            'background' => 'light',
        ], -4);

        return $this->renderer->render($area, area: $area, sections: [$section], allowH1: false);
    }

    /**
     * Services with a live page in this city, in service order.
     *
     * @return Collection<int, array{id: int, title: string, url: string, short_description: string|null}>
     */
    private function liveServices(Area $area): Collection
    {
        $live = AreaServicePage::query()->active()->where('area_id', $area->id)->pluck('service_id')->flip();

        return $area->services()->active()->ordered()->get()
            ->filter(fn (Service $s) => isset($live[$s->id]))
            ->map(fn (Service $s) => [
                'id' => $s->id,
                'title' => $s->title,
                'url' => AreaServicePage::pathFor($area->slug, $s->slug),
                'short_description' => $s->short_description,
            ])
            ->values();
    }
}
