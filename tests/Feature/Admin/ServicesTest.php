<?php

namespace Tests\Feature\Admin;

use App\Models\Area;
use App\Models\AreaServicePage;
use App\Models\Faq;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SystemPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ServicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([SettingsSeeder::class, SystemPagesSeeder::class]);
        $this->actingAs(User::factory()->create());
    }

    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'Local Moving',
            'slug' => 'local-moving',
            'short_description' => 'Fast, careful local movers.',
            'is_active' => true,
            'show_in_menu' => true,
            'show_on_home' => true,
            'faq_ids' => [],
            'seo' => ['robots_index' => true, 'robots_follow' => true],
            'sections' => [],
            ...$overrides,
        ];
    }

    public function test_a_service_is_created_with_faqs_sections_and_seo_and_placed_last()
    {
        Service::factory()->create(['sort' => 4]);
        [$a, $b] = Faq::factory()->count(2)->create()->all();

        $this->post(route('admin.services.store'), $this->payload([
            'faq_ids' => [$b->id, $a->id],
            'seo' => ['meta_title' => 'Local Movers in Dallas', 'robots_index' => true, 'robots_follow' => true],
            'sections' => [['type' => 'rich_text', 'data' => ['content' => '<p>Hi</p>'], 'is_visible' => true]],
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $service = Service::where('slug', 'local-moving')->sole();
        $this->assertSame(5, $service->sort);
        $this->assertSame([$b->id, $a->id], $service->faqs()->pluck('faqs.id')->all());
        $this->assertSame(['rich_text'], $service->sections()->pluck('type')->all());
        $this->assertSame('Local Movers in Dallas', $service->seo->meta_title);
    }

    public function test_validation()
    {
        Service::factory()->create(['slug' => 'packing']);

        $this->post(route('admin.services.store'), $this->payload(['title' => '', 'slug' => 'Packing!']))
            ->assertSessionHasErrors(['title', 'slug']);
        $this->post(route('admin.services.store'), $this->payload(['slug' => 'packing']))
            ->assertSessionHasErrors('slug');
        $this->post(route('admin.services.store'), $this->payload(['faq_ids' => [999]]))
            ->assertSessionHasErrors('faq_ids.0');
    }

    public function test_slug_change_redirects_service_and_its_city_pages()
    {
        $service = Service::factory()->create(['slug' => 'local-moving']);
        $area = Area::factory()->create(['slug' => 'plano-tx']);
        AreaServicePage::factory()->for($area)->for($service)->create();

        $this->put(route('admin.services.update', $service), $this->payload(['slug' => 'local-movers']))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('redirects', ['from_path' => '/services/local-moving', 'to_path' => '/services/local-movers']);
        $this->assertDatabaseHas('redirects', ['from_path' => '/service-areas/plano-tx/local-moving', 'to_path' => '/service-areas/plano-tx/local-movers']);
    }

    public function test_reorder_and_toggle()
    {
        [$a, $b, $c] = Service::factory()->count(3)->create()->all();

        $this->post(route('admin.services.reorder'), ['ids' => [$c->id, $a->id, $b->id]])->assertRedirect();
        $this->assertSame([$c->id, $a->id, $b->id], Service::query()->ordered()->pluck('id')->all());

        $this->patch(route('admin.services.toggle', $a), ['field' => 'show_on_home']);
        $this->assertFalse($a->fresh()->show_on_home);

        $this->patch(route('admin.services.toggle', $a), ['field' => 'title'])->assertSessionHasErrors('field');
    }

    public function test_index_and_edit_screens()
    {
        $service = Service::factory()->create(['title' => 'Storage']);
        $service->faqs()->attach(Faq::factory()->create(), ['sort' => 0]);

        $this->get(route('admin.services.index'))
            ->assertInertia(fn (Assert $page) => $page->component('services/Index')->has('services', 1)->where('services.0.title', 'Storage'));

        $this->get(route('admin.services.edit', $service))
            ->assertInertia(fn (Assert $page) => $page->component('services/Form')->has('service.faq_ids', 1)->has('sectionSchema.types'));
    }

    public function test_deleting_soft_deletes()
    {
        $service = Service::factory()->create();

        $this->delete(route('admin.services.destroy', $service))->assertRedirect(route('admin.services.index'));

        $this->assertSoftDeleted($service);
    }
}
