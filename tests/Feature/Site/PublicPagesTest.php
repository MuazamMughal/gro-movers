<?php

namespace Tests\Feature\Site;

use App\Enums\PageStatus;
use App\Enums\SystemPage;
use App\Models\Page;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SystemPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([SettingsSeeder::class, SystemPagesSeeder::class]);
    }

    public function test_home_shows_the_placeholder_until_it_has_sections()
    {
        $this->get('/')->assertOk()->assertSee('Coming soon');

        Page::system(SystemPage::Home)->sections()->create(['type' => 'hero', 'data' => ['heading' => 'Moving made easy in {city}'], 'sort' => 0]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Coming soon')
            ->assertSee('<h1 class="text-4xl font-extrabold tracking-tight text-balance sm:text-5xl lg:text-6xl">Moving made easy in Dallas</h1>', false);
    }

    public function test_published_pages_render_and_drafts_are_404()
    {
        $page = Page::factory()->create(['title' => 'Moving Checklist', 'slug' => 'moving-checklist']);
        $page->sections()->create(['type' => 'rich_text', 'data' => ['content' => '<p>Start early.</p>'], 'sort' => 0]);
        Page::factory()->draft()->create(['slug' => 'secret-draft']);

        $this->get('/moving-checklist')
            ->assertOk()
            ->assertSee('<title>Moving Checklist | Grosolve Moving Network</title>', false)
            ->assertSee('<h1 class="text-4xl font-extrabold tracking-tight text-balance md:text-5xl">Moving Checklist</h1>', false)
            ->assertSee('Start early.');

        $this->get('/secret-draft')->assertNotFound();
        $this->get('/does-not-exist')->assertNotFound();
    }

    public function test_system_pages_with_their_own_url_are_not_served_by_slug()
    {
        $this->get('/about-us')->assertOk();
        $this->get('/area-service-template')->assertNotFound();
        $this->get('/home')->assertNotFound();
    }

    public function test_preview_renders_unsaved_content_with_noindex()
    {
        $this->actingAs(User::factory()->create());
        $about = Page::system(SystemPage::About);

        $html = $this->post(route('admin.preview.page'), ['payload' => json_encode([
            'id' => $about->id,
            'title' => 'About Grosolve',
            'slug' => 'about-us',
            'seo' => ['robots_index' => true],
            'sections' => [['type' => 'hero', 'data' => ['heading' => 'Unsaved hero'], 'is_visible' => true]],
        ])])->assertOk()->getContent();

        $this->assertStringContainsString('Unsaved hero', $html);
        $this->assertStringContainsString('<meta name="robots" content="noindex,nofollow">', $html);
        $this->assertStringContainsString('Preview of unsaved changes', $html);
        $this->assertSame(0, $about->sections()->count(), 'preview saves nothing');
    }

    public function test_preview_shows_validation_problems_and_requires_login()
    {
        $this->post(route('admin.preview.page'), ['payload' => '{}'])->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create());

        $this->post(route('admin.preview.page'), ['payload' => json_encode([
            'title' => 'X', 'sections' => [['type' => 'hero', 'data' => ['heading' => '']]],
        ])])->assertOk()->assertSee('Preview not available yet')->assertSee('Hero › Heading');
    }

    public function test_unpublished_home_page_shows_placeholder()
    {
        $home = Page::system(SystemPage::Home);
        $home->sections()->create(['type' => 'hero', 'data' => ['heading' => 'Hi'], 'sort' => 0]);
        $home->update(['status' => PageStatus::Draft]);

        $this->get('/')->assertSee('Coming soon');
    }

    public function test_reloading_a_preview_tab_explains_instead_of_erroring()
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/preview/page')->assertOk()->assertSee('This preview has expired');
    }
}
