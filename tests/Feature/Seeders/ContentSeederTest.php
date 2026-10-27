<?php

namespace Tests\Feature\Seeders;

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
    }

    private function check_it_installs_the_agreed_content(): void
    {
        $this->assertSame(
            ['Local Moving', 'Long-Distance Moving', 'Residential Moving', 'Commercial Moving', 'Packing Services', 'Storage Services'],
            Service::query()->ordered()->pluck('title')->all(),
        );
        $this->assertSame(['Dallas', 'Plano', 'Irving'], Area::query()->ordered()->pluck('city')->all());
        $this->assertSame(18, AreaServicePage::query()->active()->count());
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
            $this->assertSame(2, $area->neighbors()->count());
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
        $this->assertSame(['About', 'Services', 'Service Areas', 'Reviews', 'Blog', 'Contact'], $header->tree()->pluck('label')->all());
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
