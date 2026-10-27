<?php

namespace Tests\Feature\Site;

use App\Models\Area;
use App\Models\AreaServicePage;
use App\Models\Faq;
use App\Models\Service;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SystemPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicServicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([SettingsSeeder::class, SystemPagesSeeder::class]);
    }

    public function test_a_service_without_sections_gets_hero_faqs_and_closing_cta()
    {
        $service = Service::factory()->create(['title' => 'Packing Services', 'slug' => 'packing-services', 'short_description' => 'We pack it all.']);
        $service->faqs()->attach(Faq::factory()->create(['question' => 'Do you bring boxes?']), ['sort' => 0]);

        $html = $this->get('/services/packing-services')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<h1[^>]*>Packing Services</h1>#', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('We pack it all.', $html);
        $this->assertStringContainsString('Do you bring boxes?', $html);
        $this->assertStringContainsString('Ready for a stress-free move?', $html);
        $this->assertStringContainsString('href="tel:+12145550100"', $html, 'Call Now on every service page');
        $this->assertStringContainsString('<title>Packing Services | Grosolve Moving Network</title>', $html);
    }

    public function test_own_sections_replace_the_automatic_ones()
    {
        $service = Service::factory()->create(['slug' => 'storage']);
        $service->sections()->create(['type' => 'hero', 'data' => ['heading' => 'Secure storage in {city}'], 'sort' => 0]);
        $service->sections()->create(['type' => 'cta_banner', 'data' => ['heading' => 'Book storage today'], 'sort' => 1]);

        $html = $this->get('/services/storage')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<h1[^>]*>Secure storage in Dallas</h1>#', $html);
        $this->assertStringContainsString('Book storage today', $html);
        $this->assertStringNotContainsString('Ready for a stress-free move?', $html);
    }

    public function test_cities_link_to_live_city_pages_or_the_area_page()
    {
        $service = Service::factory()->create(['title' => 'Local Moving', 'slug' => 'local-moving']);
        $dallas = Area::factory()->create(['city' => 'Dallas', 'slug' => 'dallas-tx']);
        $plano = Area::factory()->create(['city' => 'Plano', 'slug' => 'plano-tx']);
        $service->areas()->attach([$dallas->id, $plano->id]);
        AreaServicePage::factory()->for($dallas)->for($service)->create();

        $this->get('/services/local-moving')
            ->assertSee('href="/service-areas/dallas-tx/local-moving"', false)
            ->assertSee('href="/service-areas/plano-tx"', false);
    }

    public function test_inactive_and_missing_services_are_404()
    {
        Service::factory()->inactive()->create(['slug' => 'old-service']);

        $this->get('/services/old-service')->assertNotFound();
        $this->get('/services/nope')->assertNotFound();
    }

    public function test_services_index_lists_active_services()
    {
        Service::factory()->create(['title' => 'Local Moving']);
        Service::factory()->inactive()->create(['title' => 'Hidden Service']);

        $this->get('/services')
            ->assertOk()
            ->assertSee('Local Moving')
            ->assertDontSee('Hidden Service')
            ->assertSee('<link rel="canonical" href="http://localhost/services">', false);
    }

    public function test_automatic_cta_never_stacks_navy_on_navy()
    {
        Service::factory()->create(['slug' => 'bare']);

        $html = $this->get('/services/bare')->getContent();

        // Auto hero is navy, so the closing banner uses the light grey style.
        $this->assertMatchesRegularExpression('#<section\s+id="section--3"[^>]*data-theme="muted"#', $html);
    }
}
