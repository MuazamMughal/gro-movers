<?php

namespace Tests\Feature\Site;

use App\Blog\PostContent;
use App\Enums\PostStatus;
use App\Models\Author;
use App\Models\Category;
use App\Models\Media;
use App\Models\MediaVariant;
use App\Models\Post;
use Database\Seeders\BlogCategorySeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SystemPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PublicBlogTest extends TestCase
{
    use RefreshDatabase;

    private Category $tips;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([SettingsSeeder::class, SystemPagesSeeder::class, BlogCategorySeeder::class]);
        $this->tips = Category::where('slug', 'moving-tips')->sole();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_post_page_has_one_h1_toc_mid_cta_author_box_and_closing_cta()
    {
        $author = Author::factory()->create(['name' => 'Maria Lopez', 'job_title' => 'Move Coordinator', 'bio' => 'Ten years of local moves.']);
        Post::factory()->for($this->tips)->for($author)->create([
            'title' => 'Moving Checklist',
            'slug' => 'moving-checklist',
            'content' => '<h2>Eight weeks out</h2><p>Book movers.</p><h2>Two weeks out</h2><p>Pack.</p><h2>Moving day</h2><p>Relax.</p>',
        ]);

        $html = $this->get('/blog/moving-checklist')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('<h2 id="eight-weeks-out">', $html);
        $this->assertStringContainsString('href="#moving-day"', $html);
        $this->assertStringContainsString('In this article', $html);
        $this->assertStringContainsString('Planning a move?', $html);
        // The inline CTA sits right before the 3rd H2.
        $this->assertLessThan(strpos($html, 'id="moving-day"'), strpos($html, 'Planning a move?'));
        $this->assertGreaterThan(strpos($html, 'id="two-weeks-out"'), strpos($html, 'Planning a move?'));
        $this->assertStringContainsString('Written by', $html);
        $this->assertStringContainsString('Move Coordinator', $html);
        $this->assertStringContainsString('Ready for a stress-free move?', $html);
        $this->assertStringContainsString('<meta property="og:type" content="article">', $html);
        $this->assertStringContainsString('<link rel="canonical" href="'.url('/blog/moving-checklist').'">', $html);
        $this->assertStringContainsString('1 min read', $html);
    }

    public function test_short_posts_have_no_toc_or_mid_cta()
    {
        Post::factory()->for($this->tips)->create(['slug' => 'short', 'content' => '<h2>Only one</h2><p>Text.</p>']);

        $this->get('/blog/short')->assertOk()->assertDontSee('In this article')->assertDontSee('Planning a move?');
    }

    public function test_drafts_scheduled_and_deleted_posts_are_not_public()
    {
        Post::factory()->for($this->tips)->draft()->create(['slug' => 'a-draft']);
        Post::factory()->for($this->tips)->scheduled(now()->addHour())->create(['slug' => 'later']);
        Post::factory()->for($this->tips)->create(['slug' => 'gone'])->delete();

        $this->get('/blog/a-draft')->assertNotFound();
        $this->get('/blog/later')->assertNotFound();
        $this->get('/blog/gone')->assertNotFound();
        $this->get('/blog')->assertDontSee('/blog/later');
    }

    public function test_a_scheduled_post_appears_on_time_and_the_command_marks_it_published()
    {
        Carbon::setTestNow('2026-10-27 12:00:00');
        $post = Post::factory()->for($this->tips)->scheduled(Carbon::parse('2026-10-27 12:05:00'))->create(['slug' => 'on-time', 'title' => 'On time post']);

        $this->get('/blog/on-time')->assertNotFound();
        $this->artisan('blog:publish-scheduled')->assertSuccessful();
        $this->assertSame(PostStatus::Scheduled, $post->fresh()->status);

        Carbon::setTestNow('2026-10-27 12:05:00');
        $this->get('/blog/on-time')->assertOk()->assertSee('On time post');
        $this->get('/blog')->assertSee('/blog/on-time');

        $this->artisan('blog:publish-scheduled')->expectsOutputToContain('Published 1 scheduled post.')->assertSuccessful();
        $this->assertSame(PostStatus::Published, $post->fresh()->status);
    }

    public function test_the_publish_command_is_scheduled_every_minute()
    {
        $this->artisan('schedule:list')->expectsOutputToContain('blog:publish-scheduled')->assertSuccessful();
    }

    public function test_blog_index_shows_featured_post_cards_and_category_nav()
    {
        $featured = Post::factory()->for($this->tips)->create(['title' => 'Big featured guide', 'is_featured' => true]);
        Post::factory()->for($this->tips)->create(['title' => 'Regular tip']);

        $html = $this->get('/blog')->assertOk()->getContent();

        $this->assertStringContainsString('Featured', $html);
        $this->assertSame(1, substr_count($html, 'href="'.$featured->publicPath().'"'));
        $this->assertStringContainsString('Regular tip', $html);
        $this->assertStringContainsString('href="/blog/category/moving-tips"', $html);
        // Categories without live posts are left out of the nav.
        $this->assertStringNotContainsString('href="/blog/category/packing-guides"', $html);
    }

    public function test_pagination_uses_paths_and_redirects_query_strings()
    {
        Post::factory()->for($this->tips)->count(10)->create();

        $this->get('/blog')->assertOk()->assertSee('href="/blog/page/2"', false);
        $this->get('/blog/page/2')->assertOk()->assertSee('Page 2 of 2')
            ->assertSee('<link rel="canonical" href="'.url('/blog/page/2').'">', false);
        $this->get('/blog?page=2')->assertRedirect('/blog/page/2')->assertStatus(301);
        $this->get('/blog/page/1')->assertRedirect('/blog')->assertStatus(301);
        $this->get('/blog/page/3')->assertNotFound();
    }

    public function test_category_page_lists_only_its_posts()
    {
        $packing = Category::where('slug', 'packing-guides')->sole();
        Post::factory()->for($this->tips)->create(['title' => 'A moving tip']);
        Post::factory()->for($packing)->create(['title' => 'A packing guide']);

        $this->get('/blog/category/moving-tips')
            ->assertOk()
            ->assertSee('<title>Moving Tips | Grosolve Moving Network</title>', false)
            ->assertSee('A moving tip')
            ->assertDontSee('A packing guide');

        $this->get('/blog/category/moving-tips/page/2')->assertNotFound();
        $this->get('/blog/category/nope')->assertNotFound();
    }

    public function test_related_posts_and_previous_next_links()
    {
        $old = Post::factory()->for($this->tips)->create(['title' => 'Oldest', 'published_at' => now()->subDays(3)]);
        $mid = Post::factory()->for($this->tips)->create(['title' => 'Middle', 'published_at' => now()->subDays(2)]);
        $new = Post::factory()->for($this->tips)->create(['title' => 'Newest', 'published_at' => now()->subDay()]);

        $this->get($mid->publicPath())
            ->assertSee('Related articles')
            ->assertSee('Previous article')
            ->assertSee('href="'.$old->publicPath().'"', false)
            ->assertSee('Next article')
            ->assertSee('href="'.$new->publicPath().'"', false);
    }

    public function test_library_images_become_responsive_pictures_and_embeds_get_wrappers()
    {
        $media = Media::factory()->create(['alt' => 'Packed boxes', 'caption' => 'Label every box.', 'width' => 1200, 'height' => 800]);
        MediaVariant::create(['media_id' => $media->id, 'format' => 'webp', 'width' => 768, 'height' => 512, 'path' => 'media/x-768.webp', 'size' => 1000]);
        MediaVariant::create(['media_id' => $media->id, 'format' => 'jpg', 'width' => 768, 'height' => 512, 'path' => 'media/x-768.jpg', 'size' => 1000]);

        $clean = PostContent::sanitize('<p>Intro</p><img src="/old.webp" alt="" data-media-id="'.$media->id.'"><table><tbody><tr><td><p>A</p></td></tr></tbody></table><div data-youtube-video=""><iframe src="https://www.youtube-nocookie.com/embed/abc"></iframe></div>');
        $body = PostContent::render($clean);

        $this->assertStringContainsString('<picture', $body->before);
        $this->assertStringContainsString('type="image/webp"', $body->before);
        $this->assertStringContainsString('alt="Packed boxes"', $body->before);
        $this->assertStringContainsString('<figcaption>Label every box.</figcaption>', $body->before);
        $this->assertStringContainsString('loading="lazy"', $body->before);
        $this->assertStringContainsString('<div class="table-wrap"><table>', $body->before);
        $this->assertStringContainsString('<div class="video-embed"><iframe src="https://www.youtube-nocookie.com/embed/abc"', $body->before);
        $this->assertStringNotContainsString('/old.webp', $body->before);
    }

    public function test_duplicate_headings_get_unique_anchors_and_missing_images_are_dropped()
    {
        $body = PostContent::render('<h2>Tips</h2><p><img src="/x.webp" data-media-id="999"></p><h2>Tips</h2>', splitForCta: false);

        $this->assertSame([['id' => 'tips', 'text' => 'Tips'], ['id' => 'tips-2', 'text' => 'Tips']], $body->toc);
        $this->assertStringNotContainsString('<img', $body->before);
        $this->assertStringNotContainsString('<p></p>', $body->before);
    }
}
