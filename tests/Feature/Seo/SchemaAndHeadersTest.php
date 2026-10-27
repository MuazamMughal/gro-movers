<?php

namespace Tests\Feature\Seo;

use App\Enums\ReviewSource;
use App\Enums\SystemPage;
use App\Http\Middleware\HandleRedirects;
use App\Models\Area;
use App\Models\Author;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\Review;
use App\Models\Service;
use App\Seo\Schema;
use App\Support\Settings;
use Database\Seeders\BlogCategorySeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SystemPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class SchemaAndHeadersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([SettingsSeeder::class, SystemPagesSeeder::class, MenuSeeder::class, BlogCategorySeeder::class]);
    }

    /** @return list<array<string, mixed>> */
    private function graph(string $path): array
    {
        $html = $this->get($path)->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'application/ld+json'), "{$path} should print one JSON-LD graph");
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $data = json_decode($m[1], true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('https://schema.org', $data['@context']);

        return $data['@graph'];
    }

    private function node(array $graph, string $type): ?array
    {
        return collect($graph)->firstWhere('@type', $type);
    }

    public function test_every_page_has_a_moving_company_with_the_required_properties()
    {
        Area::factory()->create(['city' => 'Plano', 'state_name' => 'Texas', 'state_code' => 'TX']);
        Service::factory()->create(['title' => 'Local Moving', 'slug' => 'local-moving']);
        Settings::set('general.social', ['facebook' => 'https://facebook.com/grosolve']);
        Settings::set('general.usdot_number', '1234567');
        Settings::flush();

        $business = $this->node($this->graph('/about-us'), 'MovingCompany');

        foreach (['name', 'url', 'telephone', 'email', 'address', 'geo', 'openingHoursSpecification', 'areaServed', 'priceRange', 'sameAs', 'hasOfferCatalog'] as $property) {
            $this->assertArrayHasKey($property, $business, "MovingCompany is missing {$property}");
        }

        $this->assertSame('+12145550100', $business['telephone']);
        $this->assertSame(['@type' => 'PostalAddress', 'streetAddress' => '1234 Commerce St', 'addressLocality' => 'Dallas', 'addressRegion' => 'TX', 'postalCode' => '75201', 'addressCountry' => 'US'], $business['address']);
        $this->assertSame([['@type' => 'City', 'name' => 'Plano', 'containedInPlace' => ['@type' => 'State', 'name' => 'Texas']]], $business['areaServed']);
        $this->assertSame('Local Moving', $business['hasOfferCatalog']['itemListElement'][0]['itemOffered']['name']);
        $this->assertSame([['@type' => 'PropertyValue', 'propertyID' => 'USDOT', 'value' => '1234567']], $business['identifier']);
        $this->assertSame(['https://facebook.com/grosolve'], $business['sameAs']);
    }

    public function test_opening_hours_are_parsed_from_the_settings_rows()
    {
        $this->assertSame([
            ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'], 'opens' => '07:00', 'closes' => '19:00'],
            ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => ['Saturday', 'Sunday'], 'opens' => '08:30', 'closes' => '17:00'],
            ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => ['Monday', 'Wednesday'], 'opens' => '00:00', 'closes' => '23:59'],
        ], Schema::openingHours([
            ['days' => 'Mon–Fri', 'hours' => '7:00 AM – 7:00 PM'],
            ['days' => 'Saturday to Sunday', 'hours' => '8:30am - 5pm'],
            ['days' => 'Sun', 'hours' => 'Closed'],
            ['days' => 'Mon, Wed', 'hours' => 'Open 24 hours'],
            ['days' => 'Holidays', 'hours' => 'By appointment'],
        ]));
    }

    public function test_page_specific_nodes()
    {
        $service = Service::factory()->create(['title' => 'Local Moving', 'slug' => 'local-moving']);
        $faq = Faq::factory()->create(['question' => 'Are you insured?', 'answer' => '<p>Yes, <strong>fully</strong>.</p>']);
        $service->faqs()->attach($faq, ['sort' => 0]);
        $post = Post::factory()->for(Author::factory()->create(['name' => 'Maria Lopez']))->create(['slug' => 'checklist']);

        $graph = $this->graph('/services/local-moving');
        $this->assertSame('Local Moving', $this->node($graph, 'Service')['name']);
        $this->assertSame(url('/').'/#business', $this->node($graph, 'Service')['provider']['@id']);
        $this->assertSame('Are you insured?', $this->node($graph, 'FAQPage')['mainEntity'][0]['name']);
        $this->assertSame(['Home', 'Moving Services', 'Local Moving'], array_column($this->node($graph, 'BreadcrumbList')['itemListElement'], 'name'));

        $article = $this->node($this->graph('/blog/checklist'), 'BlogPosting');
        $this->assertSame($post->title, $article['headline']);
        $this->assertSame('Person', $article['author']['@type']);
        $this->assertSame('Maria Lopez', $article['author']['name']);
        $this->assertArrayHasKey('datePublished', $article);

        $this->assertNull($this->node($this->graph('/'), 'BreadcrumbList'), 'Home has no breadcrumbs');
    }

    public function test_aggregate_rating_only_on_reviews_and_only_from_on_site_reviews()
    {
        Review::factory()->create(['source' => ReviewSource::Website, 'rating' => 5, 'is_active' => true]);
        Review::factory()->create(['source' => ReviewSource::Website, 'rating' => 4, 'is_active' => true]);
        Review::factory()->create(['source' => ReviewSource::Google, 'rating' => 1, 'is_active' => true]);

        $rating = $this->node($this->graph('/reviews'), 'MovingCompany')['aggregateRating'];
        $this->assertSame(['@type' => 'AggregateRating', 'ratingValue' => 4.5, 'reviewCount' => 2, 'bestRating' => 5, 'worstRating' => 1], $rating);

        $this->assertArrayNotHasKey('aggregateRating', $this->node($this->graph('/about-us'), 'MovingCompany'));
    }

    public function test_robots_txt_always_ends_with_the_sitemap_line()
    {
        Settings::set('seo.robots_txt', "User-agent: *\nDisallow: /admin\nSitemap: https://old.example/sitemap.xml");
        Settings::flush();

        $body = $this->get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')->getContent();

        $this->assertStringContainsString("Disallow: /admin\n", $body);
        $this->assertStringNotContainsString('old.example', $body);
        $this->assertStringEndsWith('Sitemap: '.url('/sitemap.xml')."\n", $body);
    }

    public function test_redirects_run_before_routing_and_count_hits()
    {
        $this->withoutDefer();
        $redirect = Redirect::query()->create(['from_path' => '/old-moving-page', 'to_path' => '/services', 'status_code' => 301, 'is_active' => true]);
        Redirect::query()->create(['from_path' => '/summer-sale', 'to_path' => '/get-a-quote', 'status_code' => 302, 'is_active' => true]);
        Redirect::query()->create(['from_path' => '/paused', 'to_path' => '/services', 'is_active' => false]);

        $this->get('/old-moving-page?utm_source=flyer')->assertStatus(301)->assertRedirect('/services?utm_source=flyer');
        $this->get('/Old-Moving-Page')->assertStatus(301);
        $this->get('/summer-sale')->assertStatus(302)->assertRedirect('/get-a-quote');
        $this->get('/paused')->assertNotFound();

        $this->assertSame(2, $redirect->fresh()->hits);
        $this->assertNotNull($redirect->fresh()->last_hit_at);

        // The cached map follows changes.
        $redirect->update(['to_path' => '/service-areas']);
        $this->get('/old-moving-page')->assertRedirect('/service-areas');
    }

    public function test_slug_changes_redirect_through_the_middleware()
    {
        $service = Service::factory()->create(['slug' => 'moving']);
        $service->update(['slug' => 'local-moving']);

        $this->get('/services/moving')->assertStatus(301)->assertRedirect('/services/local-moving');
    }

    public function test_trailing_slashes_redirect_to_the_clean_url()
    {
        // The test client strips trailing slashes itself, so call the middleware directly.
        $next = fn () => response('page');
        $middleware = new HandleRedirects;

        $response = $middleware->handle(Request::create('/about-us/?utm_source=x'), $next);
        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame(url('/about-us').'?utm_source=x', $response->headers->get('Location'));

        $this->assertSame('page', $middleware->handle(Request::create('/'), $next)->getContent());
        $this->assertSame('page', $middleware->handle(Request::create('/about-us', 'POST'), $next)->getContent());
    }

    public function test_security_headers_and_the_environment_noindex_guard()
    {
        $response = $this->get('/about-us');

        $response->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy')
            // Not production (tests, local, staging): keep it out of Google.
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_production_sends_hsts_over_https_and_no_noindex_header()
    {
        $this->app['env'] = 'production';

        $response = $this->get('https://localhost/about-us');

        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains')
            ->assertHeaderMissing('X-Robots-Tag');

        $this->get('https://localhost/admin/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_search_console_and_analytics_integrations()
    {
        Settings::setMany([
            'integrations.search_console_verification' => '<meta name="google-site-verification" content="abc123XYZ" />',
            'integrations.search_console_file' => 'google1a2b3c.html',
            'integrations.gtm_id' => 'GTM-ABC1234',
            'integrations.ga4_id' => 'G-XYZ98765',
            'integrations.head_scripts' => '<meta name="custom-head" content="1">',
        ]);
        Settings::flush();

        $html = $this->get('/about-us')->getContent();

        $this->assertStringContainsString('<meta name="google-site-verification" content="abc123XYZ">', $html);
        // GA4 goes through GTM when both are set (no double counting).
        $this->assertStringContainsString('window.siteAnalytics = {"gtm":"GTM-ABC1234"}', $html);
        $this->assertStringContainsString('googletagmanager.com/ns.html?id=GTM-ABC1234', $html);
        $this->assertStringContainsString('<meta name="custom-head" content="1">', $html);
        // Tags load from the deferred script, never as blocking <script src> in the HTML.
        $this->assertStringNotContainsString('<script src="https://www.googletagmanager.com', $html);

        $this->get('/google1a2b3c.html')->assertOk()->assertSee('google-site-verification: google1a2b3c.html');
        $this->get('/googleother.html')->assertNotFound();
    }

    public function test_thank_you_is_the_only_system_page_hidden_from_google()
    {
        $hidden = collect(SystemPage::cases())
            ->filter(fn (SystemPage $key) => $key->hasPublicUrl())
            ->map(fn (SystemPage $key) => Page::system($key))
            ->reject(fn (Page $page) => $page->seoIndexable())
            ->map(fn (Page $page) => $page->system_key);

        $this->assertSame([SystemPage::ThankYou], $hidden->values()->all());
    }
}
