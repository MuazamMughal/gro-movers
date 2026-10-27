<?php

namespace Tests\Feature\Site;

use App\Enums\SystemPage;
use App\Models\Media;
use App\Models\Page;
use App\Models\Service;
use App\Models\User;
use App\Support\QuoteLink;
use App\Support\Settings;
use Database\Seeders\MenuSeeder;
use Database\Seeders\MoveTypeSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SystemPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Once;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Quote links land on the form, the address pins, favicon and the admin logo. */
class BrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([SettingsSeeder::class, SystemPagesSeeder::class, MoveTypeSeeder::class, MenuSeeder::class]);
    }

    public function test_quote_links_get_the_form_anchor()
    {
        $this->assertSame('/get-a-quote#quote-form', QuoteLink::href());
        $this->assertSame('/get-a-quote#quote-form', QuoteLink::resolve('/get-a-quote'));
        $this->assertSame('/get-a-quote/#quote-form', QuoteLink::resolve('/get-a-quote/'));
        $this->assertSame('http://localhost/get-a-quote#quote-form', QuoteLink::resolve('http://localhost/get-a-quote'));

        foreach (['/get-a-quote#other', '/get-a-quote?x=1', '/contact-us', 'https://elsewhere.example/get-a-quote', 'tel:+12145550100', null] as $unchanged) {
            $this->assertSame($unchanged, QuoteLink::resolve($unchanged));
        }
    }

    public function test_the_anchor_follows_a_renamed_quote_page()
    {
        Page::system(SystemPage::Quote)->update(['slug' => 'free-estimate']);
        Once::flush();

        $this->assertSame('/free-estimate#quote-form', QuoteLink::href());
        $this->assertSame('/get-a-quote', QuoteLink::resolve('/get-a-quote'));
    }

    public function test_the_first_quote_form_on_a_page_carries_the_anchor_once()
    {
        $html = $this->get('/get-a-quote')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'id="quote-form"'));
        $this->assertStringContainsString('href="/get-a-quote#quote-form"', $html);
        $this->assertStringNotContainsString('href="/get-a-quote"', $html);
    }

    public function test_the_closing_call_to_action_looks_the_same_everywhere()
    {
        $about = Page::system(SystemPage::About);
        // An old saved style is ignored, and the admin no longer offers the choice.
        $about->sections()->create(['type' => 'cta_banner', 'data' => ['heading' => 'Ready?', 'theme' => 'dark'], 'is_visible' => true, 'sort' => 99]);
        $service = Service::factory()->create(); // gets the automatic banner

        foreach ([$about->publicPath(), $service->publicPath()] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            preg_match_all('/<(?:section|aside)[^>]*data-theme="([a-z]+)"/', strstr($html, '<footer', true), $themes);

            $this->assertSame('muted', end($themes[1]), "{$path} should end with the light call to action");
        }

        $this->assertNotContains('theme', array_column(config('sections.types.cta_banner.fields'), 'key'));
    }

    public function test_address_fields_show_location_pins()
    {
        $html = $this->get('/get-a-quote')->assertOk()->getContent();

        // Each address input sits right after its pin icon; "Moving To" uses the pin with a check mark.
        $this->assertMatchesRegularExpression('/<\/svg>\s*<input[^>]*name="from_address"/', $html);
        $this->assertMatchesRegularExpression('/<path d="m16 18 2 2 4-4"\/>\s*<\/svg>\s*<input[^>]*name="to_address"/', $html);
    }

    public function test_favicon_is_the_logo_mark_unless_one_is_uploaded()
    {
        foreach (['/', '/admin/login'] as $path) {
            $this->get($path)->assertOk()
                ->assertSee('<link rel="icon" href="/favicon.ico"', false)
                ->assertSee('<link rel="apple-touch-icon" href="/apple-touch-icon.png">', false);
        }

        foreach (['favicon.ico', 'favicon.png', 'apple-touch-icon.png'] as $file) {
            $this->assertFileExists(public_path($file));
        }

        $favicon = Media::factory()->create();
        Settings::set('branding.favicon_id', $favicon->id);

        $this->get('/')->assertSee('<link rel="icon" href="'.$favicon->url(480).'"', false)->assertDontSee('href="/favicon.ico"', false);
    }

    public function test_admin_pages_share_the_company_logo_and_mark()
    {
        $this->get('/admin/login')->assertInertia(fn (Assert $page) => $page
            ->where('brand.name', Settings::get('general.company_name'))
            ->where('brand.logo', null)
            ->where('brand.icon', '/favicon.png'));

        $logo = Media::factory()->create();
        Settings::set('branding.logo_light_id', $logo->id);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('brand.logo', $logo->url(480))
                ->where('brand.logoDark', null));
    }
}
