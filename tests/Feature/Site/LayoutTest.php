<?php

namespace Tests\Feature\Site;

use App\Enums\SystemPage;
use App\Models\Area;
use App\Models\AreaServicePage;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Review;
use App\Models\Service;
use Database\Seeders\BlogCategorySeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SystemPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([SettingsSeeder::class, SystemPagesSeeder::class, MenuSeeder::class, BlogCategorySeeder::class]);
    }

    /** Decoded BreadcrumbList JSON-LD of a page. */
    private function breadcrumbSchema(string $html): ?array
    {
        preg_match('#<script type="application/ld\+json">(\{"@context":"https://schema.org","@type":"BreadcrumbList".*?)</script>#s', $html, $m);

        return isset($m[1]) ? json_decode($m[1], true) : null;
    }

    public function test_every_page_has_contact_bar_sticky_header_and_mobile_call_quote_bar()
    {
        $html = $this->get('/services')->assertOk()->getContent();

        $this->assertStringContainsString('data-call-location="top_bar"', $html);
        $this->assertStringContainsString('Serving Dallas–Fort Worth and moves nationwide', $html);
        $this->assertStringContainsString('<header class="sticky top-0', $html);
        $this->assertStringContainsString('aria-label="Call or get a quote"', $html);
        $this->assertStringContainsString('data-call-location="mobile_bar"', $html);
        $this->assertStringContainsString('href="tel:+12145550100"', $html);
        // The body reserves room for the fixed bar on phones (no content hidden, no layout shift).
        $this->assertMatchesRegularExpression('/<body class="[^"]*pb-16 md:pb-0/', $html);
        $this->assertStringContainsString('Skip to content', $html);
        // Tablet/desktop header: full number, plus a compact "Call Now" while the menu needs the room.
        $this->assertStringContainsString('md:inline-flex! lg:hidden! xl:inline-flex!" data-call-location="header"', $html);
        $this->assertStringContainsString('aria-label="Call (214) 555-0100" data-call-location="header"', $html);
    }

    public function test_home_has_no_breadcrumbs_and_inner_pages_do()
    {
        $home = Page::system(SystemPage::Home);
        $home->sections()->create(['type' => 'rich_text', 'data' => ['content' => '<p>Hi</p>'], 'is_visible' => true, 'sort' => 0]);

        $this->get('/')->assertOk()->assertDontSee('aria-label="Breadcrumb"', false);

        $about = Page::system(SystemPage::About);
        $html = $this->get($about->publicPath())->getContent();

        $this->assertStringContainsString('aria-label="Breadcrumb"', $html);
        $schema = $this->breadcrumbSchema($html);
        $this->assertSame(['Home', $about->title], array_column($schema['itemListElement'], 'name'));
        $this->assertSame(url('/').'/', $schema['itemListElement'][0]['item']);
        $this->assertSame(url($about->publicPath()), $schema['itemListElement'][1]['item']);
    }

    public function test_breadcrumb_trails_for_services_areas_and_blog()
    {
        $service = Service::factory()->create(['title' => 'Local Moving', 'slug' => 'local-moving']);
        $area = Area::factory()->create(['city' => 'Plano', 'state_code' => 'TX', 'slug' => 'plano-tx']);
        $area->services()->attach($service);
        AreaServicePage::factory()->for($area)->for($service)->create();
        $category = Category::where('slug', 'packing-guides')->sole();
        $post = Post::factory()->for($category)->create(['title' => 'Pack a Kitchen', 'slug' => 'pack-a-kitchen']);

        $trails = [
            '/services/local-moving' => ['Home', 'Moving Services', 'Local Moving'],
            '/service-areas/plano-tx' => ['Home', 'Service Areas', 'Plano, TX'],
            '/service-areas/plano-tx/local-moving' => ['Home', 'Service Areas', 'Plano, TX', 'Local Moving'],
            '/blog/category/packing-guides' => ['Home', 'Blog & Moving Guides', 'Packing Guides'],
            '/blog/pack-a-kitchen' => ['Home', 'Blog & Moving Guides', 'Packing Guides', 'Pack a Kitchen'],
            '/reviews' => ['Home', 'Reviews'],
        ];

        foreach ($trails as $url => $names) {
            $schema = $this->breadcrumbSchema($this->get($url)->assertOk()->getContent());
            $this->assertSame($names, array_column($schema['itemListElement'], 'name'), $url);
        }

        $schema = $this->breadcrumbSchema($this->get('/blog/pack-a-kitchen')->getContent());
        $this->assertSame(url('/blog/category/packing-guides'), $schema['itemListElement'][2]['item']);
        $this->assertSame(url('/blog/pack-a-kitchen'), $schema['itemListElement'][3]['item']);
    }

    public function test_contact_page_gets_contact_form_and_map_and_quote_page_gets_quote_form()
    {
        $contact = $this->get(Page::system(SystemPage::Contact)->publicPath())->assertOk()->getContent();
        $this->assertStringContainsString('action="/forms/contact"', $contact);
        $this->assertStringContainsString('aria-label="Contact details"', $contact);
        $this->assertStringContainsString('<iframe src="https://www.google.com/maps?q=', $contact);
        $this->assertSame(1, substr_count($contact, '<h1'));

        $quote = $this->get('/get-a-quote')->assertOk()->getContent();
        $this->assertStringContainsString('Get your free moving quote', $quote);
        $this->assertStringContainsString('action="/forms/quote"', $quote);
    }

    public function test_pages_with_their_own_form_section_do_not_get_a_second_one()
    {
        $quote = Page::system(SystemPage::Quote);
        $quote->sections()->create(['type' => 'quote_form', 'data' => ['heading' => 'Our own quote heading'], 'is_visible' => true, 'sort' => 0]);

        $html = $this->get('/get-a-quote')->getContent();
        $this->assertSame(1, substr_count($html, 'action="/forms/quote"'));
        $this->assertStringContainsString('Our own quote heading', $html);
    }

    public function test_area_page_shows_local_reviews()
    {
        $area = Area::factory()->create(['city' => 'Plano', 'state_code' => 'TX', 'slug' => 'plano-tx']);
        Review::factory()->create(['area_id' => $area->id, 'name' => 'Local Lisa', 'is_active' => true]);

        $this->get('/service-areas/plano-tx')->assertOk()->assertSee('What Plano customers say')->assertSee('Local Lisa');
    }

    public function test_areas_index_groups_cities_by_state()
    {
        Area::factory()->create(['city' => 'Plano', 'state_name' => 'Texas', 'state_code' => 'TX', 'slug' => 'plano-tx']);
        Area::factory()->create(['city' => 'Tulsa', 'state_name' => 'Oklahoma', 'state_code' => 'OK', 'slug' => 'tulsa-ok']);

        $html = $this->get('/service-areas')->assertOk()->getContent();

        $this->assertStringContainsString('>Oklahoma</h2>', $html);
        $this->assertStringContainsString('>Texas</h2>', $html);
        $this->assertLessThan(strpos($html, '>Texas</h2>'), strpos($html, '>Oklahoma</h2>'));
        $this->assertStringContainsString('href="/service-areas/tulsa-ok"', $html);
    }

    public function test_service_page_links_to_latest_posts()
    {
        Service::factory()->create(['slug' => 'local-moving']);
        Post::factory()->create(['title' => 'Moving checklist for families']);

        $this->get('/services/local-moving')->assertOk()->assertSee('Moving tips and guides')->assertSee('Moving checklist for families');
    }

    public function test_404_page_is_noindex_with_suggestions_services_and_quote_link()
    {
        Service::factory()->create(['title' => 'Packing Services', 'slug' => 'packing-services']);

        $response = $this->get('/packing-servces-dallas');

        $response->assertNotFound()
            ->assertSee('We couldn’t find that page')
            ->assertSee('Were you looking for')
            ->assertSee('href="/services/packing-services"', false)
            ->assertSee('href="/get-a-quote"', false)
            ->assertSee('<meta name="robots" content="noindex,follow">', false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_500_page_renders_without_the_database_or_vite()
    {
        Route::get('/__boom', fn () => throw new \RuntimeException('boom'));
        config(['app.debug' => false]);

        $this->get('/__boom')
            ->assertStatus(500)
            ->assertSee('Something went wrong on our side')
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false);
    }
}
