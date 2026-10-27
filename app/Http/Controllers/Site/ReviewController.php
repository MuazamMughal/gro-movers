<?php

namespace App\Http\Controllers\Site;

use App\Enums\SystemPage;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Review;
use App\Sections\AutoSections;
use App\Sections\SectionRenderer;
use App\Support\Seo;
use Illuminate\Contracts\View\View;

/** /reviews: the Reviews system page plus every active review. */
class ReviewController extends Controller
{
    public function __construct(private readonly SectionRenderer $renderer) {}

    public function index(): View
    {
        $page = Page::system(SystemPage::ReviewsIndex);
        abort_unless($page && $page->isPublished(), 404);

        $reviews = Review::query()->active()->with('photo.variants')->ordered()->get();

        $sections = AutoSections::complete($page->visibleSections()->get(), [
            'heading' => $page->title,
            'subheading' => 'Real reviews from families and businesses we have moved.',
            'theme' => 'dark',
        ], withFaq: $page->faqs()->active()->exists());
        $closing = $sections->last()?->type === 'cta_banner' ? collect([$sections->pop()]) : collect();

        return view('reviews.index', [
            'page' => $page,
            'seo' => Seo::for($page, '/reviews'),
            'rendered' => $this->renderer->render($page, sections: $sections),
            'closing' => $this->renderer->render($page, sections: $closing, allowH1: false),
            'reviews' => $reviews,
            'average' => $reviews->isEmpty() ? null : round($reviews->avg('rating'), 1),
            'breakdown' => collect([5, 4, 3, 2, 1])->mapWithKeys(fn ($stars) => [$stars => $reviews->where('rating', $stars)->count()]),
        ]);
    }
}
