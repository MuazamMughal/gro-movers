<?php

namespace Tests\Feature\Admin;

use App\Enums\MenuLocation;
use App\Enums\PageStatus;
use App\Enums\SystemPage;
use App\Models\Area;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Post;
use App\Models\Service;
use App\Models\User;
use App\Support\Menus;
use Database\Seeders\MenuSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SystemPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MenusTest extends TestCase
{
    use RefreshDatabase;

    private Menu $header;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([SettingsSeeder::class, SystemPagesSeeder::class, MenuSeeder::class]);
        $this->actingAs(User::factory()->create());
        $this->header = Menu::where('location', MenuLocation::Header)->sole();
        Menus::flush();
    }

    private function item(array $overrides = []): array
    {
        return ['label' => 'Item', 'link_type' => 'url', 'linkable_id' => null, 'url' => '/x', 'target_blank' => false, 'auto_children' => null, 'children' => [], ...$overrides];
    }

    public function test_index_opens_the_header_menu_with_link_targets()
    {
        Service::factory()->create(['title' => 'Local Moving']);

        $this->get(route('admin.menus.index'))->assertRedirect(route('admin.menus.edit', $this->header));

        $this->get(route('admin.menus.edit', $this->header))->assertInertia(fn (Assert $page) => $page
            ->component('menus/Edit')
            ->where('menu.nested', true)
            ->has('menus', 5)
            ->where('targets.service.0.name', 'Local Moving'));
    }

    public function test_saving_a_nested_menu_in_order()
    {
        $about = Page::system(SystemPage::About);
        $service = Service::factory()->create();

        $this->put(route('admin.menus.update', $this->header), [
            'title' => 'Main menu',
            'items' => [
                $this->item(['label' => 'About', 'link_type' => 'page', 'linkable_id' => $about->id, 'url' => null]),
                $this->item(['label' => 'Services', 'link_type' => 'url', 'url' => '/services', 'children' => [
                    $this->item(['label' => 'Local', 'link_type' => 'service', 'linkable_id' => $service->id, 'url' => null]),
                    $this->item(['label' => 'Quote', 'url' => '/get-a-quote', 'target_blank' => true]),
                ]]),
            ],
        ])->assertSessionHasNoErrors();

        $tree = $this->header->tree()->get();
        $this->assertSame(['About', 'Services'], $tree->pluck('label')->all());
        $this->assertSame(['Local', 'Quote'], $tree[1]->children->pluck('label')->all());
        $this->assertNull($tree[0]->url);
        $this->assertTrue($tree[1]->children[1]->target_blank);
        $this->assertSame('Main menu', $this->header->fresh()->title);
    }

    public function test_validation_of_links_and_footer_nesting()
    {
        $this->put(route('admin.menus.update', $this->header), ['title' => 'Main', 'items' => [
            $this->item(['link_type' => 'page', 'linkable_id' => null, 'url' => null]),
            $this->item(['url' => 'javascript:alert(1)']),
            $this->item(['link_type' => 'service', 'linkable_id' => 999, 'url' => null]),
        ]])->assertSessionHasErrors(['items.0.linkable_id', 'items.1.url', 'items.2.linkable_id']);

        $footer = Menu::where('location', MenuLocation::Footer1)->sole();

        $this->put(route('admin.menus.update', $footer), ['title' => 'Company', 'items' => [
            ['label' => 'A', 'link_type' => 'url', 'url' => '/a', 'target_blank' => false, 'children' => [$this->item()]],
        ]])->assertSessionHasErrors('items.0.children');

        $this->put(route('admin.menus.update', $footer), ['title' => 'Company', 'items' => [
            ['label' => 'A', 'link_type' => 'url', 'url' => '/a', 'target_blank' => false],
        ]])->assertSessionHasNoErrors();
    }

    public function test_public_menus_resolve_live_links_and_hide_dead_ones()
    {
        $about = Page::system(SystemPage::About);
        $draft = Page::factory()->create(['status' => PageStatus::Draft]);
        $active = Service::factory()->create(['title' => 'Local Moving', 'slug' => 'local-moving']);
        $inactive = Service::factory()->create(['is_active' => false]);
        $post = Post::factory()->create(['slug' => 'checklist']);

        $this->header->items()->createMany([
            ['label' => 'About', 'link_type' => 'page', 'linkable_id' => $about->id, 'sort' => 0],
            ['label' => 'Draft', 'link_type' => 'page', 'linkable_id' => $draft->id, 'sort' => 1],
            ['label' => 'Gone', 'link_type' => 'service', 'linkable_id' => $inactive->id, 'sort' => 2],
            ['label' => 'Local', 'link_type' => 'service', 'linkable_id' => $active->id, 'sort' => 3],
            ['label' => 'Guide', 'link_type' => 'post', 'linkable_id' => $post->id, 'sort' => 4, 'target_blank' => true],
        ]);

        $this->assertSame([
            ['label' => 'About', 'url' => $about->publicPath(), 'new_tab' => false, 'children' => []],
            ['label' => 'Local', 'url' => '/services/local-moving', 'new_tab' => false, 'children' => []],
            ['label' => 'Guide', 'url' => '/blog/checklist', 'new_tab' => true, 'children' => []],
        ], Menus::for(MenuLocation::Header));
    }

    public function test_auto_children_list_services_and_areas_in_menu_order()
    {
        Service::factory()->create(['title' => 'B service', 'sort' => 2]);
        Service::factory()->create(['title' => 'A service', 'sort' => 1]);
        Service::factory()->create(['title' => 'Not in menu', 'show_in_menu' => false]);
        Area::factory()->create(['city' => 'Plano', 'state_code' => 'TX', 'slug' => 'plano-tx']);

        $this->header->items()->createMany([
            ['label' => 'Services', 'link_type' => 'url', 'url' => '/services', 'auto_children' => 'services', 'sort' => 0],
            ['label' => 'Areas', 'link_type' => 'url', 'url' => '/service-areas', 'auto_children' => 'areas', 'sort' => 1],
        ]);

        $menu = Menus::for(MenuLocation::Header);
        $this->assertSame(['A service', 'B service'], array_column($menu[0]['children'], 'label'));
        $this->assertSame('/service-areas/plano-tx', $menu[1]['children'][0]['url']);
    }

    public function test_header_and_footer_render_menus_with_dropdowns()
    {
        $about = Page::system(SystemPage::About);
        Service::factory()->create(['title' => 'Local Moving', 'slug' => 'local-moving']);
        $this->header->items()->createMany([
            ['label' => 'About Us', 'link_type' => 'page', 'linkable_id' => $about->id, 'sort' => 0],
            ['label' => 'Services', 'link_type' => 'url', 'url' => '/services', 'auto_children' => 'services', 'sort' => 1],
        ]);
        $footer = Menu::where('location', MenuLocation::Footer1)->sole();
        $footer->update(['title' => 'Company']);
        $footer->items()->create(['label' => 'Contact', 'link_type' => 'url', 'url' => '/contact-us', 'sort' => 0]);

        auth()->logout();

        $html = $this->get('/services')->assertOk()->getContent();

        $this->assertStringContainsString('aria-label="Main"', $html);
        $this->assertStringContainsString('data-dropdown', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html);
        $this->assertStringContainsString('href="/services/local-moving"', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
        // Mobile drawer falls back to the header menu.
        $this->assertStringContainsString('aria-label="Mobile"', $html);
        $this->assertStringContainsString('>Company<', $html);
        $this->assertStringContainsString('href="/contact-us"', $html);
        $this->assertStringContainsString('Get a Free Quote', $html);
        $this->assertStringContainsString('© '.now()->year, $html);
    }
}
