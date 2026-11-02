<?php

namespace Tests\Feature\Seeders;

use App\Enums\MenuLinkType;
use App\Enums\MenuLocation;
use App\Enums\SystemPage;
use App\Models\Area;
use App\Models\AreaServicePage;
use App\Models\Faq;
use App\Models\Media;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Post;
use App\Models\Review;
use App\Models\Service;
use App\Models\Tag;
use App\Seo\SeoAudit;
use App\Seo\SiteIndex;
use App\Support\Settings;
use Database\Seeders\AddHowItWorksSeeder;
use Database\Seeders\AddServiceAreasSeeder;
use Database\Seeders\ContentSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Once;
use Tests\TestCase;

/** The starter content (spec §12). */
class ContentSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        // One responsive width keeps image generation fast; MediaServiceTest covers the full set.
        config(['settings.seed_content' => true, 'media.widths' => [480]]);
        $this->seed(DatabaseSeeder::class);
        Once::flush();
    }

    /** One seed, many checks: generating the placeholder images is the slow part. */
    public function test_starter_content()
    {
        $this->check_it_installs_the_agreed_content();
        $this->check_placeholder_images_exist_in_every_preset();
        $this->check_menus_and_legal_notes();
        $this->check_seo_report_is_clean_and_every_page_renders();
        $this->check_sample_reviews_are_never_review_markup();
        $this->check_new_cities_can_be_added_to_a_live_site();
        $this->check_how_it_works_can_be_added_to_a_live_site();
    }

    /** Header and footer links land next to the right items; admin menu order is kept. */
    private function check_how_it_works_can_be_added_to_a_live_site(): void
    {
        $header = Menu::query()->where('location', MenuLocation::Header->value)->sole();
        $footer = Menu::query()->where('location', MenuLocation::Footer3->value)->sole();
        $page = Page::query()->where('slug', 'how-it-works')->sole();
        $this->assertSame('How It Works', $header->items()->whereNull('parent_id')->orderBy('sort')->pluck('label')->get(3));

        // Back to a site from before the page existed.
        $header->items()->where('label', 'How It Works')->delete();
        $footer->items()->where('label', 'How It Works')->delete();
        $page->faqs()->detach();
        $page->forceDelete();

        $this->seed(AddHowItWorksSeeder::class);
        Once::flush();

        $page = Page::query()->where('slug', 'how-it-works')->sole();
        $this->assertSame(['About', 'Services', 'Service Areas', 'How It Works', 'Reviews', 'Blog', 'Contact'], $header->items()->whereNull('parent_id')->orderBy('sort')->pluck('label')->all());
        $this->assertSame(['About Us', 'How It Works', 'Reviews', 'Moving Guides', 'Contact Us', 'Get a Free Quote'], $footer->items()->orderBy('sort')->pluck('label')->all());
        $this->assertSame(5, $page->faqs()->count());

        $html = $this->get('/how-it-works')->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('How Your Move Works, Step by Step', $html);
        $this->assertStringContainsString('href="/how-it-works"', $html);
        $this->assertStringContainsString('"FAQPage"', $html);

        // Running it again changes nothing.
        $this->seed(AddHowItWorksSeeder::class);
        $this->assertSame(1, Page::query()->where('slug', 'how-it-works')->count());
        $this->assertSame(1, $header->items()->where('label', 'How It Works')->count());
    }

    /** A live site with the original 3 cities gains the rest; nothing the admin changed moves. */
    private function check_new_cities_can_be_added_to_a_live_site(): void
    {
        $original = ['dallas-tx', 'plano-tx', 'irving-tx'];
        $footer = Menu::query()->where('location', MenuLocation::Footer2->value)->sole();
        $added = Area::query()->whereNotIn('slug', $original);
        $footer->items()->whereIn('linkable_id', (clone $added)->pluck('id'))->where('link_type', MenuLinkType::Area)->delete();
        (clone $added)->get()->each->forceDelete();
        $removedByAdmin = Area::factory()->create(['slug' => 'denton-tx', 'city' => 'Old Denton']);
        $removedByAdmin->delete();

        $dallas = Area::query()->where('slug', 'dallas-tx')->sole();
        $dallas->update(['intro' => 'Edited in admin']);
        $template = Page::system(SystemPage::AreaServiceTemplate);
        $templateSections = $template->sections()->count();
        $reviews = $template->sections()->where('type', 'reviews_slider')->sole();
        $reviews->update(['data' => [...$reviews->data, 'heading' => 'What our customers in {city} say']]);
        $this->assertSame(18, AreaServicePage::query()->active()->count());

        $this->seed(AddServiceAreasSeeder::class);
        Once::flush();

        $this->assertSame(['Dallas', 'Plano', 'Irving', 'Fort Worth', 'Arlington', 'Frisco', 'McKinney', 'Garland', 'Grand Prairie'], Area::query()->ordered()->pluck('city')->all(), 'deleted cities stay deleted');
        $this->assertSame(54, AreaServicePage::query()->active()->count());
        $this->assertSame('Edited in admin', $dallas->fresh()->intro);
        $this->assertSame($templateSections, $template->sections()->count());
        $this->assertSame('What our customers say', $reviews->fresh()->data['heading']);
        $this->assertTrue($dallas->neighbors()->where('slug', 'garland-tx')->exists(), 'existing cities link to their new neighbors');
        $this->assertSame(
            ['Movers in Dallas', 'Movers in Plano', 'Movers in Irving', 'Movers in Fort Worth', 'Movers in Arlington', 'Movers in Frisco', 'Movers in McKinney', 'Movers in Garland', 'Movers in Grand Prairie', 'All service areas'],
            $footer->items()->orderBy('sort')->pluck('label')->all(),
        );

        $fortWorth = Area::query()->where('slug', 'fort-worth-tx')->sole();
        $this->assertSame(4, $fortWorth->faqs()->count());
        $this->assertSame(6, $fortWorth->services()->count());
        $this->get('/service-areas/fort-worth-tx')->assertOk()->assertSee('Moving in Fort Worth');
        $this->get('/service-areas/fort-worth-tx/local-moving')->assertOk();

        // Running it again changes nothing.
        $faqs = Faq::count();
        $this->seed(AddServiceAreasSeeder::class);
        $this->assertSame(9, Area::query()->count());
        $this->assertSame($faqs, Faq::count());
    }

    private function check_it_installs_the_agreed_content(): void
    {
        $this->assertSame(
            ['Local Moving', 'Long-Distance Moving', 'Residential Moving', 'Commercial Moving', 'Packing Services', 'Storage Services'],
            Service::query()->ordered()->pluck('title')->all(),
        );
        $this->assertSame(['Dallas', 'Plano', 'Irving', 'Fort Worth', 'Arlington', 'Frisco', 'McKinney', 'Garland', 'Grand Prairie', 'Denton'], Area::query()->ordered()->pluck('city')->all());
        $this->assertSame(60, AreaServicePage::query()->active()->count());
        $this->assertSame(9, Post::query()->published()->count());
        $this->assertSame(5, Tag::count());
        $this->assertSame(12, Review::query()->where('is_sample', true)->count());
        $this->assertGreaterThanOrEqual(15, Faq::count());

        foreach (Service::all() as $service) {
            $this->assertSame(4, $service->faqs()->count(), "{$service->title} should have 4 FAQs");
            $this->assertGreaterThanOrEqual(4, $service->sections()->count(), "{$service->title} needs a hero and 3+ sections");
            $this->assertNotNull($service->hero_media_id);
        }

        foreach (Area::all() as $area) {
            $this->assertSame(6, $area->services()->count());
            $this->assertGreaterThanOrEqual(2, $area->neighbors()->count());
            $this->assertNotEmpty($area->zip_codes);
        }

        foreach (Post::all() as $post) {
            $words = count(preg_split('/\s+/', trim(html_entity_decode(strip_tags($post->content)))));
            $this->assertGreaterThanOrEqual(800, $words, "{$post->slug} is {$words} words");
            $this->assertLessThanOrEqual(1200, $words, "{$post->slug} is {$words} words");
            $this->assertNotNull($post->featured_media_id);
        }
    }

    private function check_placeholder_images_exist_in_every_preset(): void
    {
        $this->assertEqualsCanonicalizing(['hero', 'card', 'blog', 'square'], Media::query()->distinct()->pluck('preset')->all());
        $this->assertNotNull(Settings::get('seo.default_og_image_id'));

        $media = Media::query()->where('preset', 'hero')->first();
        $this->assertSame(['jpg', 'webp'], $media->variants->pluck('format')->unique()->sort()->values()->all());
        Storage::disk('public')->assertExists($media->variants->first()->path);
        $this->assertNotEmpty($media->alt);
    }

    private function check_menus_and_legal_notes(): void
    {
        $header = Menu::query()->where('location', MenuLocation::Header)->sole();
        $this->assertSame(['About', 'Services', 'Service Areas', 'How It Works', 'Reviews', 'Blog', 'Contact'], $header->tree()->pluck('label')->all());
        $this->assertSame(['services', 'areas'], $header->tree()->get()->pluck('auto_children')->filter()->values()->all());

        foreach ([MenuLocation::Footer1, MenuLocation::Footer2, MenuLocation::Footer3] as $location) {
            $this->assertTrue(Menu::query()->where('location', $location)->sole()->items()->exists());
        }

        $this->assertStringContainsString('lawyer', Page::system(SystemPage::Privacy)->admin_note);
        $this->assertStringContainsString('lawyer', Page::system(SystemPage::Terms)->admin_note);
        $this->assertNull(Page::system(SystemPage::Home)->admin_note);
    }

    private function check_seo_report_is_clean_and_every_page_renders(): void
    {
        $report = SeoAudit::run();

        $this->assertSame(0, $report['summary']['attention'], 'pages needing SEO attention: '.collect($report['entries'])->filter(fn ($e) => $e['issues'])->pluck('path')->implode(', '));
        $this->assertSame(0, $report['summary']['duplicates']);
        $this->assertSame(0, $report['summary']['hidden_unexpected']);
        $this->assertSame(['/thank-you'], array_column($report['hidden'], 'path'));

        foreach (SiteIndex::all() as $url) {
            $html = $this->get($url->path)->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, '<h1'), "{$url->path} needs one H1");
            $this->assertDoesNotMatchRegularExpression('/\{(company|phone|city|state|state_code|service)\}/', $html, "{$url->path} shows an unreplaced token");
        }
    }

    private function check_sample_reviews_are_never_review_markup(): void
    {
        $html = $this->get('/reviews')->assertOk()->getContent();

        $this->assertStringNotContainsString('aggregateRating', $html);
    }

    public function test_running_again_changes_nothing()
    {
        Service::query()->first()->update(['title' => 'Local Moving Experts']);
        $counts = [Service::count(), Media::count(), Faq::count(), Post::count()];

        $this->seed(ContentSeeder::class);

        $this->assertSame($counts, [Service::count(), Media::count(), Faq::count(), Post::count()]);
        $this->assertSame('Local Moving Experts', Service::query()->first()->title);
    }
}
