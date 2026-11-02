<?php

namespace Tests\Feature\Admin;

use App\Enums\SystemPage;
use App\Models\Area;
use App\Models\AreaServicePage;
use App\Models\Page;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SystemPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AreaServicesTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;

    private Service $service;

    private AreaServicePage $page;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([SettingsSeeder::class, SystemPagesSeeder::class]);
        $this->actingAs(User::factory()->create());

        $this->area = Area::factory()->create(['city' => 'Plano', 'state_code' => 'TX', 'slug' => 'plano-tx']);
        $this->service = Service::factory()->create(['title' => 'Local Moving', 'slug' => 'local-moving']);
        $this->area->services()->attach($this->service);
        $this->page = AreaServicePage::factory()->for($this->area)->for($this->service)->create();
    }

    public function test_grid_shows_every_combination()
    {
        $other = Service::factory()->create();

        $this->get(route('admin.area-services.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('area-services/Index')
                ->has('services', 2)
                ->where('areas.0.cells', function ($cells) use ($other) {
                    $mine = collect($cells)->firstWhere('service_id', $this->service->id);
                    $theirs = collect($cells)->firstWhere('service_id', $other->id);

                    return $mine['offered'] && $mine['page_id'] === $this->page->id && ! $theirs['offered'];
                })
                ->where('stats.live', 1)
            );

        $this->patch(route('admin.area-services.toggle', $this->page));
        $this->assertFalse($this->page->fresh()->is_active);
    }

    public function test_template_seo_is_seeded_with_tokens_and_is_indexable()
    {
        $template = Page::system(SystemPage::AreaServiceTemplate);

        $this->assertSame('{service} in {city}, {state_code} | Free Quote', $template->seo->meta_title);
        $this->assertTrue($template->seo->robots_index);
    }

    public function test_template_edit_and_save()
    {
        $this->get(route('admin.area-services.template'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('area-services/Template')->where('usage', 1)->where('tokens.city', 'Plano'));

        $this->put(route('admin.area-services.template.update'), [
            'faq_ids' => [],
            'seo' => ['meta_title' => 'Top {service} in {city}', 'robots_index' => true, 'robots_follow' => true],
            'sections' => [['type' => 'rich_text', 'data' => ['content' => '<p>Moving in {city}?</p>'], 'is_visible' => true]],
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.area-services.template'));

        $this->assertSame(1, Page::system(SystemPage::AreaServiceTemplate)->sections()->count());
    }

    public function test_override_saves_own_content_and_can_switch_back()
    {
        $this->get(route('admin.area-services.edit', $this->page))
            ->assertInertia(fn (Assert $p) => $p->component('area-services/Edit')->where('page.heading', 'Local Moving in Plano, TX'));

        $this->put(route('admin.area-services.update', $this->page), [
            'title_override' => 'Plano Local Movers',
            'use_template' => false,
            'is_active' => true,
            'faq_ids' => [],
            'seo' => ['meta_title' => 'Plano Local Movers | Grosolve', 'robots_index' => true, 'robots_follow' => true],
            'sections' => [['type' => 'rich_text', 'data' => ['content' => '<p>Unique Plano copy</p>'], 'is_visible' => true]],
        ])->assertSessionHasNoErrors();

        $page = $this->page->fresh();
        $this->assertFalse($page->use_template);
        $this->assertSame('Plano Local Movers', $page->heading());
        $this->assertSame(1, $page->sections()->count());

        $this->put(route('admin.area-services.update', $page), [
            'title_override' => null, 'use_template' => true, 'is_active' => true, 'faq_ids' => [],
            'seo' => ['robots_index' => true, 'robots_follow' => true],
            'sections' => [['type' => 'rich_text', 'data' => ['content' => '<p>Unique Plano copy</p>'], 'is_visible' => true]],
        ]);

        $this->assertTrue($page->fresh()->use_template);
        $this->assertSame(1, $page->fresh()->sections()->count(), 'custom content is kept while the template is used');
    }
}
