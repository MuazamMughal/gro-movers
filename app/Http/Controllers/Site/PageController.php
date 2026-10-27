<?php

namespace App\Http\Controllers\Site;

use App\Enums\SystemPage;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Sections\AutoSections;
use App\Sections\SectionRenderer;
use App\Support\Seo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

/**
 * Public builder pages: the home page and every page served at "/{slug}".
 */
class PageController extends Controller
{
    public function __construct(private readonly SectionRenderer $renderer) {}

    public function home(): View
    {
        $page = Page::system(SystemPage::Home);

        // Until the home page has content, show the "coming soon" placeholder.
        if (! $page || ! $page->isPublished() || ! $page->visibleSections()->exists()) {
            return view('site.placeholder', [
                'seo' => $page ? Seo::for($page, '/') : Seo::make(title: 'Home', path: '/'),
            ]);
        }

        return $this->render($page);
    }

    public function show(string $slug): View
    {
        $page = Page::query()->published()->where('slug', $slug)->firstOrFail();

        // System pages with their own URL (services, blog…) are not served here.
        abort_if($page->system_key && ($page->system_key->fixedPath() !== null || ! $page->system_key->hasPublicUrl()), 404);

        return $this->render($page);
    }

    private function render(Page $page): View
    {
        return view('pages.show', [
            'page' => $page,
            'seo' => Seo::for($page, $page->publicPath()),
            'rendered' => $this->renderer->render($page, sections: $this->withAttachedFaqs($page)),
        ]);
    }

    /**
     * FAQs attached in the page's FAQs tab get a section of their own when the
     * page has no FAQ section yet, placed before a closing call to action.
     */
    private function withAttachedFaqs(Page $page): Collection
    {
        $sections = $page->visibleSections()->get();

        if ($sections->contains('type', 'faq') || ! $page->faqs()->active()->exists()) {
            return $sections;
        }

        $faq = AutoSections::make('faq', ['mode' => 'attached', 'heading' => 'Frequently asked questions', 'background' => 'muted'], -2);
        $position = $sections->last()?->type === 'cta_banner' ? $sections->count() - 1 : $sections->count();

        return $sections->splice(0, $position)->push($faq)->concat($sections)->values();
    }
}
