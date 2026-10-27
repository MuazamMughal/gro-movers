<?php

namespace Tests\Feature\Admin;

use App\Models\Area;
use App\Models\AreaServicePage;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SystemPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AreasTest extends TestCase
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
            'city' => 'Plano',
            'state_name' => 'Texas',
            'state_code' => 'tx',
            'slug' => 'plano-tx',
            'intro' => 'Movers serving every Plano neighborhood.',
            'lat' => '33.0198',
            'lng' => '-96.6989',
            'zip_codes' => ['75023', '75024'],
            'map_embed' => null,
            'is_active' => true,
            'show_in_menu' => true,
            'service_ids' => [],
            'neighbor_ids' => [],
            'faq_ids' => [],
            'seo' => ['robots_index' => true, 'robots_follow' => true],
            'sections' => [],
            ...$overrides,
        ];
    }

    public function test_creating_an_area_creates_a_city_page_per_offered_service()
    {
        [$local, $packing] = Service::factory()->count(2)->create()->all();
        $neighbor = Area::factory()->create();

        $this->post(route('admin.areas.store'), $this->payload([
            'service_ids' => [$local->id, $packing->id],
            'neighbor_ids' => [$neighbor->id],
        ]))->assertSessionHasNoErrors();

        $area = Area::where('slug', 'plano-tx')->sole();
        $this->assertSame('TX', $area->state_code);
        $this->assertSame(['75023', '75024'], $area->zip_codes);
        $this->assertSame([$neighbor->id], $area->neighbors()->pluck('areas.id')->all());
        $this->assertSame(2, AreaServicePage::where('area_id', $area->id)->where('is_active', true)->count());
    }

    public function test_offering_changes_follow_the_sync_rules()
    {
        [$a, $b, $c] = Service::factory()->count(3)->create()->all();
        $area = Area::factory()->create(['slug' => 'irving-tx']);
        $this->put(route('admin.areas.update', $area), $this->payload(['slug' => 'irving-tx', 'service_ids' => [$a->id, $b->id]]));

        // The admin switches B's page off in the grid; re-saving the area must not undo that.
        AreaServicePage::where(['area_id' => $area->id, 'service_id' => $b->id])->update(['is_active' => false, 'use_template' => false]);
        $this->put(route('admin.areas.update', $area), $this->payload(['slug' => 'irving-tx', 'service_ids' => [$a->id, $b->id, $c->id]]));

        $page = fn ($s) => AreaServicePage::where(['area_id' => $area->id, 'service_id' => $s->id])->first();
        $this->assertTrue($page($a)->is_active);
        $this->assertFalse($page($b)->is_active, 'grid choice kept');
        $this->assertTrue($page($c)->is_active, 'newly offered service goes live');

        // Removing A switches its page off but keeps it (and its custom content).
        $this->put(route('admin.areas.update', $area), $this->payload(['slug' => 'irving-tx', 'service_ids' => [$b->id, $c->id]]));
        $this->assertFalse($page($a)->is_active);
        $this->assertNotNull($page($a));
        $this->assertFalse($page($b)->use_template);
    }

    public function test_validation()
    {
        $area = Area::factory()->create();

        $this->post(route('admin.areas.store'), $this->payload([
            'city' => '',
            'state_code' => 'TEX',
            'zip_codes' => ['7502'],
            'map_embed' => 'https://evil.example/embed',
            'neighbor_ids' => [999],
        ]))->assertSessionHasErrors(['city', 'state_code', 'zip_codes.0', 'map_embed', 'neighbor_ids.0']);

        $this->put(route('admin.areas.update', $area), $this->payload(['slug' => $area->slug, 'neighbor_ids' => [$area->id]]))
            ->assertSessionHasErrors('neighbor_ids.0');
    }

    public function test_slug_change_redirects_city_and_city_service_pages()
    {
        $service = Service::factory()->create(['slug' => 'packing']);
        $area = Area::factory()->create(['slug' => 'plano-tx']);
        $this->put(route('admin.areas.update', $area), $this->payload(['service_ids' => [$service->id]]));

        $this->put(route('admin.areas.update', $area), $this->payload(['slug' => 'plano-texas', 'service_ids' => [$service->id]]));

        $this->assertDatabaseHas('redirects', ['from_path' => '/service-areas/plano-tx', 'to_path' => '/service-areas/plano-texas']);
        $this->assertDatabaseHas('redirects', ['from_path' => '/service-areas/plano-tx/packing', 'to_path' => '/service-areas/plano-texas/packing']);
    }

    public function test_index_reorder_and_toggle()
    {
        [$a, $b] = Area::factory()->count(2)->create()->all();

        $this->get(route('admin.areas.index'))->assertInertia(fn (Assert $p) => $p->component('areas/Index')->has('areas', 2));

        $this->post(route('admin.areas.reorder'), ['ids' => [$b->id, $a->id]]);
        $this->assertSame([$b->id, $a->id], Area::query()->ordered()->pluck('id')->all());

        $this->patch(route('admin.areas.toggle', $a), ['field' => 'show_in_menu']);
        $this->assertFalse($a->fresh()->show_in_menu);
    }
}
