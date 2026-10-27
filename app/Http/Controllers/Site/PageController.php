<?php

namespace App\Http\Controllers\Site;

use App\Enums\SystemPage;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Sections\AutoSections;
use App\Sections\SectionRenderer;
use App\Support\Breadcrumbs;
use App\Support\Seo;
use App\Support\Settings;
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
            'breadcrumbs' => Breadcrumbs::page($page),
            'rendered' => $this->renderer->render($page, sections: $this->completeSections($page)),
        ]);
    }

    /**
     * Fill gaps so system pages always do their job:
     * - Contact gets the contact form (with phone, email, address, hours) and a map.
     * - Get a Free Quote gets a prominent quote form.
     * - FAQs attached in the FAQs tab get a section when the page has none.
     * Forms go right after the hero; FAQs go before a closing call to action.
     */
    private function completeSections(Page $page): Collection
    {
        $sections = $page->visibleSections()->get()->values();
        $types = $sections->pluck('type');
        $top = [];

        // Thank-you page before the admin has written one: confirmation and next steps.
        if ($page->system_key === SystemPage::ThankYou && $sections->isEmpty()) {
            return collect([
                AutoSections::make('hero', [
                    'eyebrow' => 'Request received',
                    'heading' => 'Thank you!',
                    'subheading' => (string) Settings::get('forms.success_message', 'We received your request and will contact you shortly.'),
                    'primary_button' => ['label' => 'Back to the home page', 'url' => '/'],
                    'secondary_button' => ['label' => 'Read our moving guides', 'url' => '/blog'],
                    'theme' => 'dark',
                ], -1),
                AutoSections::make('steps', [
                    'heading' => 'What happens next',
                    'steps' => [
                        ['title' => 'We review your move', 'text' => 'A move coordinator looks at your addresses, date and type of move.'],
                        ['title' => 'You get your quote', 'text' => 'We call or email you with a clear, upfront price, usually the same business day.'],
                        ['title' => 'Book your date', 'text' => 'Happy with the price? Lock in your moving date with no hidden fees.'],
                    ],
                    'background' => 'light',
                ], -8),
            ]);
        }

        if ($page->system_key === SystemPage::Contact && ! $types->contains('contact_form')) {
            $top[] = AutoSections::make('contact_form', ['heading' => 'Send us a message', 'intro' => 'We reply within one business day. For a price, the quote form is fastest.', 'show_details' => true], -5);

            if (! $types->contains('map')) {
                $top[] = AutoSections::make('map', ['height' => 'md'], -6);
            }
        }

        if ($page->system_key === SystemPage::Quote && ! $types->contains('quote_form')) {
            $top[] = AutoSections::make('quote_form', [
                'heading' => 'Get your free moving quote',
                'intro' => 'Tell us about your move. We reply with a clear, upfront price, usually the same day.',
                'bullets' => [['text' => 'No obligation, no hidden fees'], ['text' => 'Licensed and insured movers'], ['text' => 'Local and long-distance moves']],
                'background' => 'muted',
            ], -7);
        }

        if ($top !== []) {
            $at = $sections->first()?->type === 'hero' ? 1 : 0;
            $sections->splice($at, 0, $top);
        }

        if (! $types->contains('faq') && $page->faqs()->active()->exists()) {
            $faq = AutoSections::make('faq', ['mode' => 'attached', 'heading' => 'Frequently asked questions', 'background' => 'muted'], -2);
            $sections->splice($sections->last()?->type === 'cta_banner' ? $sections->count() - 1 : $sections->count(), 0, [$faq]);
        }

        return $sections;
    }
}
