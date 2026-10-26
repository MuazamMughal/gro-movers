<?php

namespace Tests\Feature\Models;

use App\Enums\SystemPage;
use App\Models\Area;
use App\Models\AreaServicePage;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlugRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_changing_a_service_slug_redirects_its_page_and_area_service_pages()
    {
        $service = Service::factory()->create(['slug' => 'local-moving']);
        $area = Area::factory()->create(['slug' => 'dallas-tx']);
        AreaServicePage::factory()->for($area)->for($service)->create();

        $service->update(['slug' => 'local-movers']);

        $this->assertRedirect('/services/local-moving', '/services/local-movers');
        $this->assertRedirect('/service-areas/dallas-tx/local-moving', '/service-areas/dallas-tx/local-movers');
    }

    public function test_changing_an_area_slug_redirects_its_page_and_area_service_pages()
    {
        $area = Area::factory()->create(['slug' => 'plano-tx']);
        $service = Service::factory()->create(['slug' => 'packing-services']);
        AreaServicePage::factory()->for($area)->for($service)->create();

        $area->update(['slug' => 'plano-texas']);

        $this->assertRedirect('/service-areas/plano-tx', '/service-areas/plano-texas');
        $this->assertRedirect('/service-areas/plano-tx/packing-services', '/service-areas/plano-texas/packing-services');
    }

    public function test_posts_categories_and_custom_pages_redirect_on_slug_change()
    {
        $post = Post::factory()->create(['slug' => 'old-post']);
        $category = Category::factory()->create(['slug' => 'old-cat']);
        $page = Page::factory()->create(['slug' => 'old-page']);

        $post->update(['slug' => 'new-post']);
        $category->update(['slug' => 'new-cat']);
        $page->update(['slug' => 'new-page']);

        $this->assertRedirect('/blog/old-post', '/blog/new-post');
        $this->assertRedirect('/blog/category/old-cat', '/blog/category/new-cat');
        $this->assertRedirect('/old-page', '/new-page');
    }

    public function test_fixed_path_system_pages_do_not_create_redirects()
    {
        $page = Page::factory()->create(['system_key' => SystemPage::ServicesIndex, 'slug' => 'services']);

        $page->update(['slug' => 'our-services']);

        $this->assertSame('/services', $page->publicPath());
        $this->assertSame(0, Redirect::count());
    }

    public function test_redirect_chains_are_flattened_and_loops_removed()
    {
        $service = Service::factory()->create(['slug' => 'a']);

        $service->update(['slug' => 'b']);
        $service->update(['slug' => 'c']);

        $this->assertRedirect('/services/a', '/services/c');
        $this->assertRedirect('/services/b', '/services/c');

        // Going back to an earlier slug makes that URL live again.
        $service->update(['slug' => 'a']);

        $this->assertDatabaseMissing('redirects', ['from_path' => '/services/a']);
        $this->assertRedirect('/services/b', '/services/a');
        $this->assertRedirect('/services/c', '/services/a');
    }

    private function assertRedirect(string $from, string $to): void
    {
        $this->assertDatabaseHas('redirects', [
            'from_path' => $from,
            'to_path' => $to,
            'status_code' => 301,
            'is_active' => true,
        ]);
    }
}
