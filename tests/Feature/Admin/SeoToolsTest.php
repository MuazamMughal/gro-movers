<?php

namespace Tests\Feature\Admin;

use App\Enums\SystemPage;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\Service;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SystemPagesSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SeoToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([SettingsSeeder::class, SystemPagesSeeder::class]);
        $this->actingAs(User::factory()->create());
    }

    public function test_report_lists_fallback_seo_duplicates_and_hidden_pages()
    {
        Service::factory()->create(['title' => 'Local Moving', 'slug' => 'local-moving'])->seo()->create(['meta_title' => 'Movers Dallas', 'robots_index' => true, 'robots_follow' => true]);
        Service::factory()->create(['title' => 'Long Distance', 'slug' => 'long-distance'])->seo()->create(['meta_title' => 'Movers Dallas', 'robots_index' => true, 'robots_follow' => true]);

        $this->get(route('admin.seo.report'))->assertInertia(fn (Assert $page) => $page
            ->component('seo/Report')
            ->where('summary.hidden', 1)
            ->where('summary.hidden_unexpected', 0)
            ->where('hidden.0.path', '/thank-you')
            ->where('duplicates', fn ($groups) => collect($groups)->contains(fn ($g) => $g['field'] === 'title' && count($g['urls']) === 2))
            ->where('entries', fn ($entries) => collect($entries)->firstWhere('path', '/about-us')['issues'][0]['code'] === 'title_fallback'));
    }

    public function test_dashboard_shows_pages_hidden_from_google()
    {
        Page::system(SystemPage::About)->seo()->updateOrCreate([], ['robots_index' => false, 'robots_follow' => true]);

        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->has('hiddenFromGoogle', 2)
            ->where('hiddenFromGoogle', fn ($h) => collect($h)->firstWhere('path', '/about-us')['expected'] === false));
    }

    public function test_live_uniqueness_check_ignores_the_page_itself()
    {
        $about = Page::system(SystemPage::About);
        $about->seo()->updateOrCreate([], ['meta_title' => 'About our Dallas movers', 'robots_index' => true, 'robots_follow' => true]);

        $this->postJson(route('admin.seo.check'), ['path' => '/contact-us', 'title' => 'About our Dallas movers'])
            ->assertOk()
            ->assertJsonPath('clashes.0.field', 'title')
            ->assertJsonPath('clashes.0.path', '/about-us');

        $this->postJson(route('admin.seo.check'), ['path' => '/about-us', 'title' => 'About our Dallas movers'])
            ->assertJsonCount(0, 'clashes');
    }

    public function test_redirect_crud_and_validation()
    {
        $this->post(route('admin.seo.redirects.store'), ['from_path' => 'https://grosolve.com/Old-Page/', 'to_path' => '/services', 'status_code' => 301])
            ->assertSessionHasNoErrors();
        $redirect = Redirect::sole();
        $this->assertSame('/old-page', $redirect->from_path);

        $this->post(route('admin.seo.redirects.store'), ['from_path' => '/old-page', 'to_path' => '/x', 'status_code' => 301])->assertSessionHasErrors('from_path');
        $this->post(route('admin.seo.redirects.store'), ['from_path' => '/loop', 'to_path' => '/loop', 'status_code' => 301])->assertSessionHasErrors('to_path');
        $this->post(route('admin.seo.redirects.store'), ['from_path' => '/admin', 'to_path' => '/x', 'status_code' => 301])->assertSessionHasErrors('from_path');
        $this->post(route('admin.seo.redirects.store'), ['from_path' => '/a', 'to_path' => 'javascript:alert(1)', 'status_code' => 301])->assertSessionHasErrors('to_path');

        $this->put(route('admin.seo.redirects.update', $redirect), ['from_path' => '/old-page', 'to_path' => '/service-areas', 'status_code' => 302, 'is_active' => false])->assertSessionHasNoErrors();
        $this->assertSame(302, $redirect->fresh()->status_code);
        $this->assertFalse($redirect->fresh()->is_active);

        $this->delete(route('admin.seo.redirects.destroy', $redirect));
        $this->assertSame(0, Redirect::count());
    }

    public function test_redirects_csv_import()
    {
        Redirect::query()->create(['from_path' => '/existing', 'to_path' => '/old-target']);
        $csv = "from,to,status\n/existing,/services\nhttps://old.site/About-Old/,/about-us,302\n/bad,not a url\n/self,/self\n";

        $this->post(route('admin.seo.redirects.import'), ['file' => UploadedFile::fake()->createWithContent('redirects.csv', $csv)])
            ->assertSessionHasNoErrors();

        $this->assertSame('/services', Redirect::where('from_path', '/existing')->value('to_path'));
        $this->assertSame(302, Redirect::where('from_path', '/about-old')->value('status_code'));
        $this->assertSame(2, Redirect::count());
    }

    public function test_robots_editor_blocks_disallow_all_in_production()
    {
        $this->put(route('admin.seo.robots.update'), ['robots' => "User-agent: *\nDisallow: /private"])->assertSessionHasNoErrors();
        $this->assertSame("User-agent: *\nDisallow: /private", Settings::get('seo.robots_txt'));

        // Outside the "testing" environment Laravel checks CSRF again; this test is about the robots rule.
        $this->app['env'] = 'production';
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->put(route('admin.seo.robots.update'), ['robots' => "User-agent: *\nDisallow: /"])->assertSessionHasErrors('robots');
        $this->assertSame("User-agent: *\nDisallow: /private", Settings::get('seo.robots_txt'));
    }

    public function test_sitemap_regenerate()
    {
        $this->post(route('admin.seo.sitemap.regenerate'))->assertRedirect();

        $this->assertStringContainsString('<loc>'.url('/about-us').'</loc>', $this->get('/sitemap.xml')->getContent());
    }
}
