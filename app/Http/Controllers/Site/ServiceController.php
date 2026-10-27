<?php

namespace App\Http\Controllers\Site;

use App\Enums\SystemPage;
use App\Http\Controllers\Controller;
use App\Models\AreaServicePage;
use App\Models\Page;
use App\Models\Post;
use App\Models\Service;
use App\Sections\AutoSections;
use App\Sections\SectionRenderer;
use App\Support\Breadcrumbs;
use App\Support\Seo;
use Illuminate\Contracts\View\View;

class ServiceController extends Controller
{
    public function __construct(private readonly SectionRenderer $renderer) {}

    /** /services: the "Moving Services" system page, always listing every service. */
    public function index(): View
    {
        $page = Page::system(SystemPage::ServicesIndex);
        abort_unless($page && $page->isPublished(), 404);

        $sections = $page->visibleSections()->get();

        if (! $sections->contains('type', 'services_grid')) {
            $sections->push(AutoSections::make('services_grid', ['mode' => 'all', 'background' => 'light'], -1));
        }

        $sections = AutoSections::complete($sections, ['heading' => $page->title, 'theme' => 'dark'], withFaq: $page->faqs()->exists());

        return view('pages.show', [
            'page' => $page,
            'seo' => Seo::for($page, '/services'),
            'breadcrumbs' => Breadcrumbs::services(),
            'rendered' => $this->renderer->render($page, sections: $sections),
        ]);
    }

    /** /services/{slug} */
    public function show(string $slug): View
    {
        $service = Service::query()->active()->where('slug', $slug)->with('seo')->firstOrFail();

        $sections = AutoSections::complete(
            $service->visibleSections()->get(),
            [
                'heading' => $service->title,
                'subheading' => $service->short_description,
                'background_image' => $service->hero_media_id,
                'primary_button' => ['label' => 'Get a Free Quote', 'url' => '/get-a-quote'],
                'theme' => 'dark',
            ],
            withFaq: $service->faqs()->active()->exists(),
        );

        // The closing call to action is rendered separately so the city list sits just above it.
        $closing = $sections->last()?->type === 'cta_banner' ? collect([$sections->pop()]) : collect();

        return view('services.show', [
            'service' => $service,
            'seo' => Seo::for($service, $service->publicPath()),
            'breadcrumbs' => Breadcrumbs::service($service),
            'rendered' => $this->renderer->render($service, service: $service, sections: $sections),
            'closing' => $this->renderer->render($service, service: $service, sections: $closing, allowH1: false),
            'areas' => $this->areasFor($service),
            'posts' => Post::query()->published()->latestFirst()->with(['category', 'featuredImage.variants'])->limit(3)->get(),
        ]);
    }

    /**
     * Cities offering this service, linking to the "{service} in {city}" page when it is live.
     *
     * @return list<array{name: string, url: string}>
     */
    private function areasFor(Service $service): array
    {
        $cityPages = AreaServicePage::query()->active()->where('service_id', $service->id)->pluck('area_id')->flip();

        return $service->areas()->active()->ordered()->get()
            ->map(fn ($area) => [
                'name' => $area->name,
                'url' => isset($cityPages[$area->id]) ? AreaServicePage::pathFor($area->slug, $service->slug) : $area->publicPath(),
            ])
            ->all();
    }
}
