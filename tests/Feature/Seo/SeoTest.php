<?php

namespace Tests\Feature\Seo;

use App\Enums\SystemPage;
use App\Models\Page;
use App\Models\Post;
use App\Models\Service;
use App\Services\Media\MediaService;
use App\Support\Seo;
use App\Support\SeoForm;
use App\Support\Settings;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SystemPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        Storage::fake('public');
        config(['app.url' => 'http://localhost']);
    }

    public function test_fallback_title_description_canonical_and_og_values()
    {
        $service = Service::factory()->create([
            'title' => 'Local Moving',
            'slug' => 'local-moving',
            'short_description' => 'Fast, careful local movers across Dallas–Fort Worth.',
        ]);

        $seo = Seo::for($service, $service->publicPath());

        $this->assertSame('Local Moving | Grosolve Moving Network', $seo->title);
        $this->assertSame('Fast, careful local movers across Dallas–Fort Worth.', $seo->description);
        $this->assertSame('http://localhost/services/local-moving', $seo->canonical);
        $this->assertSame('index,follow', $seo->robots());
        $this->assertSame('Local Moving', $seo->ogTitle, 'social title drops the site suffix');
        $this->assertSame('website', $seo->ogType);
        $this->assertNull($seo->image);
    }

    public function test_custom_values_override_fallbacks()
    {
        $service = Service::factory()->create(['slug' => 'packing']);
        SeoForm::save($service, [
            'meta_title' => 'Packing Services in Dallas | Grosolve',
            'meta_description' => 'Professional packing.',
            'og_title' => 'We pack, you relax',
            'canonical_url' => '/services/full-packing',
            'robots_index' => false,
            'robots_follow' => true,
        ]);

        $seo = Seo::for($service->fresh(), $service->publicPath());

        $this->assertSame('Packing Services in Dallas | Grosolve', $seo->title);
        $this->assertSame('We pack, you relax', $seo->ogTitle);
        $this->assertSame('Professional packing.', $seo->ogDescription);
        $this->assertSame('http://localhost/services/full-packing', $seo->canonical);
        $this->assertSame('noindex,follow', $seo->robots());
    }

    public function test_share_image_falls_back_from_og_image_to_record_image_to_site_default()
    {
        $media = app(MediaService::class);
        $hero = $media->upload(UploadedFile::fake()->image('hero.jpg', 1920, 800), 'hero');
        $og = $media->upload(UploadedFile::fake()->image('og.jpg', 1200, 630), 'blog');
        $default = $media->upload(UploadedFile::fake()->image('default.jpg', 1200, 630), 'blog');
        Settings::set('seo.default_og_image_id', $default->id);

        $withNothing = Service::factory()->create();
        $withHero = Service::factory()->create(['hero_media_id' => $hero->id]);
        $withOg = Service::factory()->create(['hero_media_id' => $hero->id]);
        SeoForm::save($withOg, ['og_image_id' => $og->id]);

        $this->assertSame($default->id, Seo::for($withNothing, '/x')->image->id);
        $this->assertSame($hero->id, Seo::for($withHero, '/x')->image->id);
        $this->assertSame($og->id, Seo::for($withOg->fresh(), '/x')->image->id);

        $seo = Seo::for($withOg->fresh(), '/x');
        $this->assertMatchesRegularExpression('#^http://localhost/storage/media/.+-1200\.jpg$#', $seo->imageUrl());
        $this->assertSame([1200, 630], $seo->imageSize());
    }

    public function test_posts_are_articles_and_paginated_pages_get_own_title_and_canonical()
    {
        $post = Post::factory()->create(['title' => 'Packing Tips']);
        $this->assertSame('article', Seo::for($post, $post->publicPath())->ogType);

        $blog = Page::factory()->create(['system_key' => SystemPage::BlogIndex, 'title' => 'Moving Guides', 'slug' => 'blog']);
        $page2 = Seo::for($blog, '/blog', page: 2);

        $this->assertSame('Moving Guides – Page 2 | Grosolve Moving Network', $page2->title);
        $this->assertSame('http://localhost/blog/page/2', $page2->canonical);
    }

    public function test_absolute_urls_use_the_canonical_host_and_https_in_production()
    {
        $this->assertSame('http://localhost/blog', Seo::absoluteUrl('/blog/'));
        $this->assertSame('http://localhost/', Seo::absoluteUrl('/'));
        $this->assertSame('https://other.example/x', Seo::absoluteUrl('https://other.example/x'));

        Settings::set('seo.canonical_host', 'grosolve.com');
        $this->app['env'] = 'production';

        $this->assertSame('https://grosolve.com/services/local-moving', Seo::absoluteUrl('services/local-moving'));
    }

    public function test_home_page_fallback_title_leads_with_city()
    {
        $this->seed(SystemPagesSeeder::class);

        $this->assertSame('Movers in Dallas, TX | Grosolve Moving Network', Page::system(SystemPage::Home)->seoTitle());
    }

    public function test_seo_form_normalizes_and_validates()
    {
        $service = Service::factory()->create();

        $seo = SeoForm::save($service, ['meta_title' => '  Local   Movers  ', 'meta_description' => '   ', 'og_image_id' => '']);

        $this->assertSame('Local Movers', $seo->meta_title);
        $this->assertNull($seo->meta_description);
        $this->assertNull($seo->og_image_id);
        $this->assertTrue($seo->robots_index, 'missing robots flags default to indexable');
        $this->assertTrue($seo->robots_follow);

        $rules = SeoForm::rules();
        $this->assertTrue(Validator::make(['seo' => ['canonical_url' => '/ok/path']], $rules)->passes());
        $this->assertTrue(Validator::make(['seo' => ['canonical_url' => 'https://example.com/a']], $rules)->passes());
        $this->assertFalse(Validator::make(['seo' => ['canonical_url' => 'javascript:alert(1)']], $rules)->passes());
        $this->assertFalse(Validator::make(['seo' => ['og_image_id' => 999]], $rules)->passes());
    }

    public function test_props_expose_values_fallbacks_and_url_for_the_panel()
    {
        $service = Service::factory()->create(['title' => 'Storage', 'slug' => 'storage', 'short_description' => 'Safe storage.']);

        $props = SeoForm::props($service, $service->publicPath());

        $this->assertSame('Storage | Grosolve Moving Network', $props['fallbacks']['title']);
        $this->assertSame('Safe storage.', $props['fallbacks']['description']);
        $this->assertSame('http://localhost/services/storage', $props['url']);
        $this->assertTrue($props['values']['robots_index']);

        $new = SeoForm::props(null, null);
        $this->assertNull($new['url']);
        $this->assertNull($new['values']['meta_title']);
    }

    public function test_head_component_renders_escaped_tags()
    {
        $service = Service::factory()->create(['title' => 'Moves "Done" <Right>', 'slug' => 'right']);

        $html = Blade::render('<x-site.seo :seo="$seo" />', ['seo' => Seo::for($service, '/services/right')]);

        $this->assertStringContainsString('<title>Moves &quot;Done&quot; &lt;Right&gt; | Grosolve Moving Network</title>', $html);
        $this->assertStringContainsString('<link rel="canonical" href="http://localhost/services/right">', $html);
        $this->assertStringContainsString('<meta name="robots" content="index,follow">', $html);
        $this->assertStringContainsString('<meta property="og:type" content="website">', $html);
        $this->assertStringContainsString('<meta name="twitter:card" content="summary">', $html);
    }

    public function test_public_home_page_outputs_seo_tags()
    {
        $this->seed(SystemPagesSeeder::class);

        $this->get('/')
            ->assertOk()
            ->assertSee('<title>Movers in Dallas, TX | Grosolve Moving Network</title>', false)
            ->assertSee('<link rel="canonical" href="http://localhost/">', false);
    }
}
