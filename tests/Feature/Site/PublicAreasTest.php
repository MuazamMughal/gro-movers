<?php

namespace Tests\Feature\Site;

use App\Enums\SystemPage;
use App\Models\Area;
use App\Models\AreaServicePage;
use App\Models\Page;
use App\Models\Service;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SystemPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicAreasTest extends TestCase
{
    use RefreshDatabase;

    private Area $plano;

    private Service $local;

    private Service $packing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([SettingsSeeder::class, SystemPagesSeeder::class]);

        $this->plano = Area::factory()->create(['city' => 'Plano', 'state_code' => 'TX', 'slug' => 'plano-tx', 'intro' => 'Plano movers since 2015.']);
        $this->local = Service::factory()->create(['title' => 'Local Moving', 'slug' => 'local-moving', 'short_description' => 'Across town, done right.']);
        $this->packing = Service::factory()->create(['title' => 'Packing Services', 'slug' => 'packing-services']);
        $this->plano->services()->attach([$this->local->id, $this->packing->id]);
        AreaServicePage::factory()->for($this->plano)->for($this->local)->create();
        AreaServicePage::factory()->for($this->plano)->for($this->packing)->create();
    }

    public function test_area_page_has_hero_services_and_cta()
    {
        $neighbor = Area::factory()->create(['city' => 'Frisco', 'state_code' => 'TX']);
        $this->plano->neighbors()->attach($neighbor);

        $html = $this->get('/service-areas/plano-tx')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<h1[^>]*>Movers in Plano, TX</h1>#', $html);
        $this->assertStringContainsString('Plano movers since 2015.', $html);
        $this->assertStringContainsString('href="/service-areas/plano-tx/local-moving"', $html);
        $this->assertStringContainsString('Movers in Frisco, TX', $html);
        $this->assertStringContainsString('href="tel:+12145550100"', $html);
        $this->assertStringContainsString('<title>Movers in Plano, TX | Grosolve Moving Network</title>', $html);
    }

    public function test_template_pages_fill_tokens_in_content_and_seo()
    {
        $template = Page::system(SystemPage::AreaServiceTemplate);
        $template->sections()->create(['type' => 'rich_text', 'data' => ['heading' => 'Why {city} picks us for {service}', 'content' => '<p>x</p>'], 'sort' => 0]);

        $html = $this->get('/service-areas/plano-tx/local-moving')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<h1[^>]*>Local Moving in Plano, TX</h1>#', $html);
        $this->assertStringContainsString('Why Plano picks us for Local Moving', $html);
        $this->assertStringContainsString('<title>Local Moving in Plano, TX | Grosolve Moving Network</title>', $html);
        $this->assertStringContainsString('Looking for Local Moving in Plano, TX?', $html);
        $this->assertStringContainsString('<link rel="canonical" href="http://localhost/service-areas/plano-tx/local-moving">', $html);
        $this->assertStringContainsString('<meta name="robots" content="index,follow">', $html);
        $this->assertStringContainsString('Packing Services in Plano', $html, 'sibling services are linked');
        $this->assertStringContainsString('href="/services/local-moving"', $html, 'parent service is linked');
    }

    public function test_custom_pages_use_their_own_content_and_seo()
    {
        $page = AreaServicePage::where('service_id', $this->packing->id)->first();
        $page->update(['use_template' => false, 'title_override' => 'Plano Packing Pros']);
        $page->sections()->create(['type' => 'rich_text', 'data' => ['content' => '<p>Only on this page</p>'], 'sort' => 0]);
        $page->seo()->create(['meta_title' => 'Plano Packers', 'robots_index' => true]);

        $this->get('/service-areas/plano-tx/packing-services')
            ->assertOk()
            ->assertSee('Only on this page')
            ->assertSee('<title>Plano Packers</title>', false)
            ->assertSee('Plano Packing Pros');
    }

    public function test_pages_are_404_when_off_unoffered_or_inactive()
    {
        AreaServicePage::where('service_id', $this->packing->id)->update(['is_active' => false]);
        $this->get('/service-areas/plano-tx/packing-services')->assertNotFound();

        $this->plano->services()->detach($this->local);
        $this->get('/service-areas/plano-tx/local-moving')->assertNotFound();

        $this->plano->update(['is_active' => false]);
        $this->get('/service-areas/plano-tx')->assertNotFound();
    }

    public function test_areas_index_lists_active_areas()
    {
        Area::factory()->inactive()->create(['city' => 'Ghosttown']);

        $this->get('/service-areas')->assertOk()->assertSee('Movers in Plano, TX')->assertDontSee('Ghosttown');
    }
}
