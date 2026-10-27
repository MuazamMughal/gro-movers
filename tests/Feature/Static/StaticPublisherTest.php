<?php

namespace Tests\Feature\Static;

use App\Enums\SystemPage;
use App\Jobs\BuildStaticPages;
use App\Jobs\RebuildPaths;
use App\Models\Area;
use App\Models\AreaServicePage;
use App\Models\Category;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Post;
use App\Models\Review;
use App\Models\Service;
use App\Models\StaticBuild;
use App\Models\User;
use App\Services\Static\HtmlMinifier;
use App\Services\Static\StaticInvalidator;
use App\Services\Static\StaticPublisher;
use App\Support\Settings;
use Database\Seeders\BlogCategorySeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\MoveTypeSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SystemPagesSeeder;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StaticPublisherTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    private StaticPublisher $publisher;

    protected function setUp(): void
    {
        parent::setUp();

        // Never touch the real public/static in tests.
        $this->root = storage_path('framework/testing/static-'.bin2hex(random_bytes(4)));
        config(['static.path' => $this->root]);

        $this->seed([SettingsSeeder::class, SystemPagesSeeder::class, MenuSeeder::class, MoveTypeSeeder::class, BlogCategorySeeder::class]);
        $this->publisher = app(StaticPublisher::class);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    private function file(string $path): string
    {
        return $this->publisher->fileFor($path);
    }

    public function test_file_paths_mirror_the_url()
    {
        $this->assertSame($this->root.'/index.html', $this->file('/'));
        $this->assertSame($this->root.'/services/local-moving/index.html', $this->file('/services/local-moving'));
        $this->assertSame($this->root.'/blog/page/2/index.html', $this->file('/blog/page/2/'));
    }

    public function test_building_a_page_writes_minified_html_and_logs_it()
    {
        $this->assertSame('ok', $this->publisher->build('/about-us'));

        $html = file_get_contents($this->file('/about-us'));
        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('<title>About Us | Grosolve Moving Network</title>', $html);
        // Markup whitespace is collapsed (inline <script>/<style> keep theirs).
        $markup = preg_replace('#<(script|style)\b.*?</\1>#s', '', $html);
        $this->assertStringNotContainsString("\n", trim($markup));
        $this->assertStringContainsString('<head> <meta charset="utf-8">', $html);
        $this->assertStringContainsString('application/ld+json', $html);
        // Nothing visitor- or session-specific is baked into the file.
        $this->assertStringNotContainsString('csrf-token', $html);
        $this->assertStringNotContainsString('laravel_session', $html);
        $this->assertSame([], glob(dirname($this->file('/about-us')).'/*.tmp'), 'no temp files left behind');

        $this->assertDatabaseHas('static_builds', ['path' => '/about-us', 'status' => 'ok']);
    }

    public function test_pages_that_no_longer_exist_or_redirect_lose_their_file()
    {
        $service = Service::factory()->create(['slug' => 'moving']);
        $this->publisher->build('/services/moving');
        $this->assertFileExists($this->file('/services/moving'));

        $service->update(['slug' => 'local-moving']); // 301 from the old URL now

        $this->assertSame('removed', $this->publisher->build('/services/moving'));
        $this->assertFileDoesNotExist($this->file('/services/moving'));
        $this->assertDirectoryDoesNotExist($this->root.'/services/moving');
        $this->assertDatabaseHas('static_builds', ['path' => '/services/moving', 'status' => 'removed', 'error' => 'HTTP 301']);

        $this->assertSame('removed', $this->publisher->build('/no-such-page'));
    }

    public function test_paths_cover_every_public_page_and_blog_pagination()
    {
        $tips = Category::where('slug', 'moving-tips')->sole();
        Post::factory()->for($tips)->count(12)->create();
        Service::factory()->create(['slug' => 'local-moving']);

        $paths = $this->publisher->paths();

        foreach (['/', '/about-us', '/thank-you', '/services/local-moving', '/blog', '/blog/page/2', '/blog/category/moving-tips', '/blog/category/moving-tips/page/2'] as $expected) {
            $this->assertContains($expected, $paths);
        }

        $this->assertNotContains('/blog/page/3', $paths);
        $this->assertNotContains('/blog/category/packing-guides', $paths, 'empty categories are not built');
    }

    public function test_build_all_runs_in_chunks_and_removes_orphans()
    {
        config(['static.chunk' => 4]);
        File::ensureDirectoryExists($this->root.'/old-page');
        file_put_contents($this->root.'/old-page/index.html', 'stale');

        $count = $this->publisher->buildAll(); // sync queue: runs right away

        $this->assertSame(count($this->publisher->paths()), $count);
        $this->assertSame($count, $this->publisher->fileCount());
        $this->assertFileExists($this->file('/'));
        $this->assertFileDoesNotExist($this->root.'/old-page/index.html');
    }

    public function test_build_all_dispatches_a_named_batch()
    {
        Bus::fake();
        config(['static.chunk' => 5]);

        $count = $this->publisher->buildAll();

        Bus::assertBatched(fn (PendingBatch $batch) => $batch->name === StaticPublisher::BATCH_NAME
            && $batch->jobs->count() === (int) ceil($count / 5)
            && $batch->jobs->every(fn ($job) => $job instanceof BuildStaticPages));
    }

    public function test_switching_the_cache_off_deletes_every_file_at_once()
    {
        $this->publisher->build('/about-us');
        $this->publisher->build('/');
        config(['static.auto' => true]);
        Queue::fake();

        Settings::set('static.enabled', false);
        Settings::flush();

        $this->assertSame(0, $this->publisher->fileCount());
        $this->assertSame('removed', $this->publisher->build('/about-us'));
        $this->assertFileDoesNotExist($this->file('/about-us'));
    }

    public function test_minifier_keeps_pre_script_and_textarea_intact()
    {
        $html = "<div>\n    <p>Hello   <b>big</b>\n world</p><!-- note -->\n<pre>  keep\n  this</pre>\n<script>\nlet a = '  x  ';\n</script><textarea> a\n b</textarea></div>";

        $this->assertSame("<div> <p>Hello <b>big</b> world</p> <pre>  keep\n  this</pre> <script>\nlet a = '  x  ';\n</script><textarea> a\n b</textarea></div>\n", HtmlMinifier::minify($html));
    }

    public function test_static_forms_carry_their_own_start_time_and_no_session_token()
    {
        $this->publisher->build('/get-a-quote');

        $html = file_get_contents($this->file('/get-a-quote'));
        $this->assertMatchesRegularExpression('/name="_started" value="\d+\.[a-f0-9]{64}"/', $html);
        $this->assertStringNotContainsString('name="_token"', $html);
    }

    // ---- dependency map ---------------------------------------------------------------------

    private function pending(callable $change): array
    {
        config(['static.auto' => true]);
        Queue::fake();
        $invalidator = app(StaticInvalidator::class);
        $invalidator->dispatch(); // start empty
        $change();

        return $invalidator->pending();
    }

    public function test_page_edits_rebuild_that_page_and_its_old_url()
    {
        $about = Page::system(SystemPage::About);

        $pending = $this->pending(fn () => $about->update(['title' => 'About our movers', 'slug' => 'about']));

        $this->assertFalse($pending['full']);
        $this->assertEqualsCanonicalizing(['/about', '/about-us'], $pending['paths']);
    }

    public function test_service_content_edits_are_targeted_but_renames_rebuild_everything()
    {
        $service = Service::factory()->create(['slug' => 'local-moving']);
        $plano = Area::factory()->create(['slug' => 'plano-tx']);
        $plano->services()->attach($service);
        AreaServicePage::factory()->for($plano)->for($service)->create();

        $pending = $this->pending(fn () => $service->update(['short_description' => 'New text']));
        $this->assertFalse($pending['full']);
        $this->assertEqualsCanonicalizing(['/services/local-moving', '/services', '/', '/service-areas/plano-tx/local-moving', '/service-areas/plano-tx'], $pending['paths']);

        $this->assertTrue($this->pending(fn () => $service->update(['title' => 'Local Movers']))['full'], 'menus and schema list service names');
        $this->assertTrue($this->pending(fn () => Service::factory()->create())['full']);
    }

    public function test_sections_seo_and_template_changes()
    {
        $service = Service::factory()->create(['slug' => 'local-moving']);
        $area = Area::factory()->create(['slug' => 'plano-tx']);
        $area->services()->attach($service);
        AreaServicePage::factory()->for($area)->for($service)->create(['use_template' => true]);

        $this->assertSame(['/services/local-moving'], $this->pending(fn () => $service->sections()->create(['type' => 'rich_text', 'data' => [], 'is_visible' => true, 'sort' => 0]))['paths']);
        $this->assertSame(['/services/local-moving'], $this->pending(fn () => $service->seo()->create(['meta_title' => 'X', 'robots_index' => true, 'robots_follow' => true]))['paths']);

        $template = Page::system(SystemPage::AreaServiceTemplate);
        $this->assertSame(['/service-areas/plano-tx/local-moving'], $this->pending(fn () => $template->sections()->create(['type' => 'rich_text', 'data' => [], 'is_visible' => true, 'sort' => 0]))['paths']);
    }

    public function test_posts_reviews_and_sitewide_changes()
    {
        $service = Service::factory()->create(['slug' => 'local-moving']);
        $area = Area::factory()->create(['slug' => 'plano-tx']);

        $post = $this->pending(fn () => Post::factory()->for(Category::where('slug', 'moving-tips')->sole())->create(['slug' => 'checklist']))['paths'];
        foreach (['/blog/checklist', '/blog', '/blog/category/moving-tips', '/services/local-moving'] as $path) {
            $this->assertContains($path, $post);
        }

        $review = $this->pending(fn () => Review::factory()->create(['area_id' => $area->id]))['paths'];
        foreach (['/reviews', '/', '/service-areas/plano-tx'] as $path) {
            $this->assertContains($path, $review);
        }

        $this->assertTrue($this->pending(fn () => Menu::query()->first()->update(['title' => 'Main']))['full']);
        $this->assertTrue($this->pending(fn () => Settings::set('general.phone', '(214) 555-0199'))['full']);
    }

    public function test_one_job_is_queued_per_request_with_all_paths()
    {
        config(['static.auto' => true]);
        Queue::fake();
        $this->actingAs(User::factory()->create());

        $about = Page::system(SystemPage::About);
        $about->update(['title' => 'A']);
        Page::system(SystemPage::Contact)->update(['title' => 'B']);
        app(StaticInvalidator::class)->dispatch();

        Queue::assertPushed(RebuildPaths::class, 1);
        Queue::assertPushed(RebuildPaths::class, fn (RebuildPaths $job) => ! $job->full && count($job->paths) === 2);
    }

    public function test_auto_rebuild_is_off_unless_enabled()
    {
        config(['static.auto' => false]);
        Queue::fake();

        Page::system(SystemPage::About)->update(['title' => 'Changed']);
        app(StaticInvalidator::class)->dispatch();

        Queue::assertNothingPushed();
    }

    // ---- admin ------------------------------------------------------------------------------

    public function test_dashboard_status_and_rebuild_button()
    {
        $this->actingAs(User::factory()->create());
        $this->publisher->build('/about-us');

        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('staticSite.enabled', true)
            ->where('staticSite.files', 1)
            ->where('staticSite.running', null)
            ->has('staticSite.failures', 0));

        Bus::fake();
        $count = count($this->publisher->paths());
        $this->post(route('admin.static.rebuild'))->assertRedirect()->assertInertiaFlash('toast.message', "Site rebuilding… ({$count} pages)");
        Bus::assertBatched(fn (PendingBatch $b) => $b->name === StaticPublisher::BATCH_NAME);
    }

    public function test_failed_builds_show_on_the_dashboard()
    {
        $this->actingAs(User::factory()->create());
        StaticBuild::query()->create(['path' => '/broken', 'status' => 'failed', 'error' => 'View not found', 'built_at' => now()]);

        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('staticSite.failures.0.path', '/broken'));
    }

    public function test_artisan_commands()
    {
        $this->artisan('static:build', ['paths' => ['/about-us', '/contact-us']])->expectsOutputToContain('Built 2 pages.')->assertSuccessful();
        $this->assertSame(2, $this->publisher->fileCount());

        $this->artisan('static:clear')->expectsOutputToContain('Removed 2 static pages.')->assertSuccessful();
        $this->assertSame(0, $this->publisher->fileCount());
    }
}
