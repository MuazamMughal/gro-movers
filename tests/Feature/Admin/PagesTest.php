<?php

namespace Tests\Feature\Admin;

use App\Enums\PageStatus;
use App\Enums\SystemPage;
use App\Models\Page;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SystemPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([SettingsSeeder::class, SystemPagesSeeder::class]);
        $this->actingAs(User::factory()->create());
    }

    public function test_index_lists_system_and_custom_pages_but_not_the_area_service_template()
    {
        Page::factory()->create(['title' => 'Moving Checklist', 'slug' => 'moving-checklist']);

        $this->get(route('admin.pages.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pages/Index')
                ->has('pages', count(SystemPage::cases()))  // 11 system (minus template) + 1 custom
                ->where('pages.0.title', 'Home')
                ->where('pages.0.path', '/')
                ->where('pages.0.is_system', true)
                ->where('pages', fn ($pages) => collect($pages)->doesntContain('title', 'Area × Service Template'))
            );
    }

    public function test_a_custom_page_is_created_with_sections_and_seo()
    {
        $this->post(route('admin.pages.store'), [
            'title' => 'Moving Checklist',
            'slug' => 'moving-checklist',
            'status' => 'published',
            'seo' => ['meta_title' => 'Free Moving Checklist', 'robots_index' => true, 'robots_follow' => true],
            'sections' => [
                ['type' => 'hero', 'data' => ['heading' => 'Your moving checklist'], 'is_visible' => true],
                ['type' => 'rich_text', 'data' => ['content' => '<p>Start 8 weeks out.</p>'], 'is_visible' => true],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $page = Page::where('slug', 'moving-checklist')->sole();
        $this->assertSame(PageStatus::Published, $page->status);
        $this->assertNotNull($page->published_at);
        $this->assertSame(['hero', 'rich_text'], $page->sections()->pluck('type')->all());
        $this->assertSame('Free Moving Checklist', $page->seo->meta_title);
    }

    public function test_slug_rules()
    {
        $base = ['title' => 'X', 'status' => 'draft', 'sections' => []];

        $this->post(route('admin.pages.store'), [...$base, 'slug' => 'Bad Slug!'])->assertSessionHasErrors('slug');
        $this->post(route('admin.pages.store'), [...$base, 'slug' => 'services'])->assertSessionHasErrors('slug');
        $this->post(route('admin.pages.store'), [...$base, 'slug' => 'about-us'])->assertSessionHasErrors('slug');
        $this->post(route('admin.pages.store'), [...$base, 'slug' => 'free-guide'])->assertSessionHasNoErrors();
    }

    public function test_section_errors_are_reported_with_readable_names()
    {
        $this->post(route('admin.pages.store'), [
            'title' => 'X', 'slug' => 'x-page', 'status' => 'draft',
            'sections' => [['type' => 'hero', 'data' => ['heading' => '']]],
        ])->assertSessionHasErrors(['sections.0.data.heading' => 'The Hero › Heading field is required.']);
    }

    public function test_fixed_url_system_pages_cannot_change_their_slug_but_others_redirect()
    {
        $home = Page::system(SystemPage::Home);
        $about = Page::system(SystemPage::About);

        $this->put(route('admin.pages.update', $home), ['title' => 'Home', 'slug' => 'new-home', 'status' => 'published', 'sections' => []])
            ->assertSessionHasErrors('slug');
        $this->put(route('admin.pages.update', $home), ['title' => 'Welcome', 'status' => 'published', 'sections' => []])
            ->assertSessionHasNoErrors();

        $this->put(route('admin.pages.update', $about), ['title' => 'About', 'slug' => 'about', 'status' => 'published', 'sections' => []])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('redirects', ['from_path' => '/about-us', 'to_path' => '/about']);
    }

    public function test_unpublishing_keeps_the_first_publish_date()
    {
        $page = Page::factory()->create(['slug' => 'guide', 'status' => PageStatus::Published, 'published_at' => now()->subMonth()]);
        $first = $page->published_at;

        $this->put(route('admin.pages.update', $page), ['title' => $page->title, 'slug' => 'guide', 'status' => 'draft', 'sections' => []]);

        $page->refresh();
        $this->assertSame(PageStatus::Draft, $page->status);
        $this->assertTrue($first->equalTo($page->published_at));
    }

    public function test_system_pages_cannot_be_deleted_and_custom_pages_go_to_trash()
    {
        $this->delete(route('admin.pages.destroy', Page::system(SystemPage::Contact)));
        $this->assertNotNull(Page::system(SystemPage::Contact));

        $custom = Page::factory()->create();
        $this->delete(route('admin.pages.destroy', $custom))->assertRedirect(route('admin.pages.index'));
        $this->assertSoftDeleted($custom);
    }

    public function test_the_area_service_template_is_not_editable_here()
    {
        $this->get(route('admin.pages.edit', Page::system(SystemPage::AreaServiceTemplate)))->assertNotFound();
    }

    public function test_edit_screen_props()
    {
        $about = Page::system(SystemPage::About);

        $this->get(route('admin.pages.edit', $about))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pages/Form')
                ->where('page.slug_locked', false)
                ->where('page.path', '/about-us')
                ->has('sectionSchema.types.hero')
                ->has('sectionOptions.services')
                ->where('seo.url', 'http://localhost/about-us')
            );

        $this->get(route('admin.pages.edit', Page::system(SystemPage::Home)))
            ->assertInertia(fn (Assert $page) => $page->where('page.slug_locked', true));
    }
}
