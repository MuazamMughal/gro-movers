<?php

namespace Tests\Feature\Seo;

use App\Enums\SystemPage;
use App\Models\Area;
use App\Models\AreaServicePage;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Service;
use App\Seo\SiteIndex;
use App\Seo\SiteUrl;
use App\Support\Settings;
use Database\Seeders\BlogCategorySeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\MoveTypeSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SystemPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Once;
use Tests\TestCase;

/**
 * Renders every indexable URL of a small but complete site and checks what
 * Google sees (spec §9 heading audit, indexability, CTA presence, mixed content).
 */
class SiteAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([SettingsSeeder::class, SystemPagesSeeder::class, MenuSeeder::class, MoveTypeSeeder::class, BlogCategorySeeder::class]);

        $home = Page::system(SystemPage::Home);
        $home->sections()->create(['type' => 'hero', 'data' => ['heading' => 'Stress-free movers in Dallas–Fort Worth'], 'is_visible' => true, 'sort' => 0]);

        $local = Service::factory()->create(['title' => 'Local Moving', 'slug' => 'local-moving']);
        $packing = Service::factory()->create(['title' => 'Packing Services', 'slug' => 'packing-services']);
        $plano = Area::factory()->create(['city' => 'Plano', 'state_code' => 'TX', 'state_name' => 'Texas', 'slug' => 'plano-tx']);
        $irving = Area::factory()->create(['city' => 'Irving', 'state_code' => 'TX', 'state_name' => 'Texas', 'slug' => 'irving-tx']);

        foreach ([$plano, $irving] as $area) {
            $area->services()->attach([$local->id, $packing->id]);
            AreaServicePage::factory()->for($area)->for($local)->create();
            AreaServicePage::factory()->for($area)->for($packing)->create();
        }

        Post::factory()->for(Category::where('slug', 'moving-tips')->sole())->create(['title' => 'Moving Checklist for Families', 'slug' => 'moving-checklist']);
        Post::factory()->for(Category::where('slug', 'packing-guides')->sole())->create(['title' => 'How to Pack a Kitchen', 'slug' => 'pack-a-kitchen']);
        Post::factory()->draft()->create(['slug' => 'secret-draft']);

        // Every indexable record gets its own description (as the real seed content does).
        foreach (SiteIndex::all() as $url) {
            $model = $url->model;
            $model->seo()->updateOrCreate([], ['meta_description' => "Unique description for {$url->label}: licensed movers, upfront pricing and a free quote from our local team today.", 'robots_index' => $url->index, 'robots_follow' => true]);
        }

        // The area × service template description uses tokens, so each city page is unique.
        Page::system(SystemPage::AreaServiceTemplate)->seo->update([
            'meta_description' => 'Looking for {service} in {city}, {state_code}? {company} provides licensed, insured movers with upfront pricing.',
        ]);
        Once::flush();
    }

    /** @return array{0: string, 1: string|null, 2: list<string>} title, description, H1s */
    private function seoHead(string $html): array
    {
        preg_match('#<title>(.*?)</title>#s', $html, $title);
        preg_match('#<meta name="description" content="([^"]*)">#', $html, $description);
        preg_match_all('#<h1\b[^>]*>(.*?)</h1>#s', $html, $h1);

        return [
            html_entity_decode($title[1] ?? '', ENT_QUOTES | ENT_HTML5),
            isset($description[1]) ? html_entity_decode($description[1], ENT_QUOTES | ENT_HTML5) : null,
            array_map(fn ($h) => trim(html_entity_decode(strip_tags($h), ENT_QUOTES | ENT_HTML5)), $h1[1]),
        ];
    }

    public function test_every_indexable_url_has_one_h1_and_a_unique_title_and_description()
    {
        $seen = ['title' => [], 'description' => [], 'h1' => []];
        $urls = SiteIndex::indexable();

        $this->assertGreaterThanOrEqual(20, $urls->count());

        foreach ($urls as $url) {
            $html = $this->get($url->path)->assertOk()->getContent();
            [$title, $description, $h1s] = $this->seoHead($html);

            $this->assertCount(1, $h1s, "{$url->path} must have exactly one <h1>");
            $this->assertNotSame('', $title, "{$url->path} has an empty title");
            $this->assertLessThanOrEqual(70, mb_strlen($title), "{$url->path} title is too long: {$title}");
            $this->assertNotNull($description, "{$url->path} has no meta description");
            $this->assertLessThanOrEqual(170, mb_strlen($description), "{$url->path} description is too long");

            // The index (sitemap, report, admin warnings) must describe the page as rendered.
            $this->assertSame($url->title, $title, "{$url->path}: index title differs from the page");
            $this->assertSame($url->h1, $h1s[0], "{$url->path}: index H1 differs from the page");

            foreach (['title' => $title, 'description' => $description, 'h1' => $h1s[0]] as $field => $value) {
                $key = mb_strtolower($value);
                $this->assertArrayNotHasKey($key, $seen[$field], "Duplicate {$field} on {$url->path} and ".($seen[$field][$key] ?? ''));
                $seen[$field][$key] = $url->path;
            }
        }
    }

    public function test_important_pages_are_indexable_and_in_the_sitemap()
    {
        $sitemap = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
        $xml = simplexml_load_string($sitemap);
        $this->assertNotFalse($xml, 'sitemap.xml is not valid XML');
        $locs = collect();
        foreach ($xml->url as $entry) {
            $locs->push((string) $entry->loc);
        }
        $paths = $locs->map(fn ($l) => parse_url($l, PHP_URL_PATH) ?: '/');

        $expected = [
            '/', '/about-us', '/services', '/service-areas', '/reviews', '/blog', '/contact-us', '/get-a-quote', '/privacy-policy', '/terms',
            '/services/local-moving', '/services/packing-services',
            '/service-areas/plano-tx', '/service-areas/irving-tx',
            '/service-areas/plano-tx/local-moving', '/service-areas/irving-tx/packing-services',
            '/blog/moving-checklist', '/blog/pack-a-kitchen', '/blog/category/moving-tips',
        ];

        foreach ($expected as $path) {
            $this->assertContains($path, $paths, "{$path} missing from sitemap");
            $this->assertStringContainsString('<meta name="robots" content="index,follow">', $this->get($path)->getContent(), "{$path} is not index,follow");
        }

        foreach (['/thank-you', '/blog/secret-draft', '/blog/category/moving-cost-guides'] as $path) {
            $this->assertNotContains($path, $paths, "{$path} must not be in the sitemap");
        }

        $this->assertStringContainsString('<meta name="robots" content="noindex,follow">', $this->get('/thank-you')->getContent());
    }

    public function test_noindex_and_foreign_canonicals_leave_the_sitemap()
    {
        // Model saves (as in the admin) clear the cached sitemap.
        Service::where('slug', 'packing-services')->sole()->seo->update(['robots_index' => false]);
        Page::system(SystemPage::Terms)->seo->update(['canonical_url' => '/privacy-policy']);
        Once::flush();

        $sitemap = $this->get('/sitemap.xml')->getContent();

        $this->assertStringNotContainsString('/services/packing-services<', $sitemap);
        $this->assertStringNotContainsString('/terms<', $sitemap);
        $this->assertStringContainsString('/services/local-moving<', $sitemap);
    }

    public function test_every_page_has_a_call_link_and_a_quote_link()
    {
        foreach (SiteIndex::all() as $url) {
            $html = $this->get($url->path)->assertOk()->getContent();

            $this->assertStringContainsString('href="tel:+12145550100"', $html, "{$url->path} has no Call Now link");
            $this->assertMatchesRegularExpression('#href="(https?://[^"]+)?/get-a-quote\#quote-form"#', $html, "{$url->path} has no Get a Free Quote link");
        }
    }

    public function test_no_mixed_content_when_served_over_https()
    {
        config(['app.url' => 'https://grosolve.test']);
        URL::forceScheme('https');
        URL::forceRootUrl('https://grosolve.test');
        Settings::flush();

        foreach (['/', '/services/local-moving', '/service-areas/plano-tx/local-moving', '/blog/moving-checklist', '/reviews'] as $path) {
            // The Vite dev server (npm run dev) is plain http on localhost; production serves /build.
            $html = str_replace('http://localhost:5173', '', $this->get('https://grosolve.test'.$path)->assertOk()->getContent());

            $this->assertDoesNotMatchRegularExpression('#(src|srcset|poster|action)="http://#', $html, "{$path} loads something over http://");
            $this->assertDoesNotMatchRegularExpression('#<link[^>]+href="http://#', $html, "{$path} links a stylesheet or canonical over http://");
            $this->assertStringContainsString('<link rel="canonical" href="https://grosolve.test', $html);
            $this->assertStringNotContainsString('"http://grosolve.test', $html);
        }

        $this->assertStringContainsString('<loc>https://grosolve.test/services/local-moving</loc>', $this->get('https://grosolve.test/sitemap.xml')->getContent());
    }

    public function test_the_index_matches_the_admin_edit_links()
    {
        $types = SiteIndex::all()->groupBy('type')->map->count();

        $this->assertSame(2, $types['service']);
        $this->assertSame(2, $types['area']);
        $this->assertSame(4, $types['area_service']);
        $this->assertSame(2, $types['post']);
        $this->assertSame(2, $types['category']);

        SiteIndex::all()->each(fn (SiteUrl $u) => $this->assertStringStartsWith('/admin/', $u->editUrl));
    }
}
