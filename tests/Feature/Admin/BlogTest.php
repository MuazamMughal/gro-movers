<?php

namespace Tests\Feature\Admin;

use App\Enums\PostStatus;
use App\Models\Author;
use App\Models\Category;
use App\Models\Media;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\Tag;
use App\Models\User;
use App\Services\Media\MediaUsage;
use App\Support\Settings;
use Database\Seeders\BlogCategorySeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SystemPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BlogTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([SettingsSeeder::class, SystemPagesSeeder::class, BlogCategorySeeder::class]);
        $this->actingAs(User::factory()->create());
        $this->category = Category::where('slug', 'moving-tips')->sole();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'The Ultimate Moving Checklist',
            'slug' => 'moving-checklist',
            'category_id' => $this->category->id,
            'author_id' => null,
            'excerpt' => 'Everything to do before moving day.',
            'content' => '<h2>Plan</h2><p>Start early.</p>',
            'featured_media_id' => null,
            'status' => 'published',
            'published_at' => null,
            'is_featured' => false,
            'tags' => [],
            'seo' => ['robots_index' => true, 'robots_follow' => true],
            ...$overrides,
        ];
    }

    public function test_the_three_client_categories_are_seeded_idempotently()
    {
        $this->category->update(['name' => 'Tips & Tricks']);
        $this->seed(BlogCategorySeeder::class);

        $this->assertSame(['moving-tips', 'packing-guides', 'moving-cost-guides'], Category::ordered()->pluck('slug')->all());
        $this->assertSame('Tips & Tricks', $this->category->fresh()->name);
    }

    public function test_publishing_without_a_date_goes_live_now_and_sanitizes_content()
    {
        $this->post(route('admin.posts.store'), $this->payload([
            'content' => '<h2>Plan</h2><p onclick="x()">Start early.</p><script>alert(1)</script><iframe src="https://evil.example/x"></iframe>',
        ]))->assertSessionHasNoErrors();

        $post = Post::sole();
        $this->assertSame(PostStatus::Published, $post->status);
        $this->assertTrue($post->isLive());
        $this->assertSame('<h2>Plan</h2><p>Start early.</p>', $post->content);
        $this->assertSame(1, $post->reading_minutes);
    }

    public function test_reading_time_is_calculated_from_the_content()
    {
        $this->post(route('admin.posts.store'), $this->payload([
            'content' => '<p>'.str_repeat('word ', 650).'</p>',
        ]));

        $this->assertSame(4, Post::sole()->reading_minutes);
    }

    public function test_a_future_date_schedules_the_post_in_the_site_time_zone()
    {
        Carbon::setTestNow('2026-10-27 15:00:00'); // 10:00 in Dallas (CDT)
        Settings::set('general.timezone', 'America/Chicago');
        Settings::flush();

        $this->post(route('admin.posts.store'), $this->payload(['published_at' => '2026-10-28T09:30']))
            ->assertSessionHasNoErrors();

        $post = Post::sole();
        $this->assertSame(PostStatus::Scheduled, $post->status);
        $this->assertSame('2026-10-28 14:30:00', $post->published_at->utc()->format('Y-m-d H:i:s'));
        $this->assertFalse($post->isLive());
        $this->get('/blog/moving-checklist')->assertNotFound();

        // The edit form shows the time back in local time.
        $this->get(route('admin.posts.edit', $post))->assertInertia(fn (Assert $page) => $page
            ->where('post.published_at', '2026-10-28T09:30')
            ->where('post.state', 'scheduled')
            ->where('post.status', 'published'));
    }

    public function test_drafts_keep_their_date_but_stay_off_the_site()
    {
        $this->post(route('admin.posts.store'), $this->payload(['status' => 'draft', 'published_at' => '2026-01-01T08:00']));

        $post = Post::sole();
        $this->assertSame(PostStatus::Draft, $post->status);
        $this->assertNotNull($post->published_at);
        $this->assertSame('draft', $post->state());
        $this->get('/blog/moving-checklist')->assertNotFound();
    }

    public function test_tags_are_created_by_name_and_matched_case_insensitively()
    {
        Tag::create(['name' => 'Packing Tips', 'slug' => 'packing-tips']);

        $this->post(route('admin.posts.store'), $this->payload(['tags' => ['packing tips', 'Budget ', 'budget']]))
            ->assertSessionHasErrors('tags.2');

        $this->post(route('admin.posts.store'), $this->payload(['tags' => ['packing tips', 'Budget ']]))
            ->assertSessionHasNoErrors();

        $this->assertSame(['budget', 'packing-tips'], Post::sole()->tags()->orderBy('slug')->pluck('slug')->all());
        $this->assertSame(2, Tag::count());
    }

    public function test_reserved_and_duplicate_slugs_are_rejected()
    {
        Post::factory()->create(['slug' => 'taken']);

        $this->post(route('admin.posts.store'), $this->payload(['slug' => 'page']))->assertSessionHasErrors('slug');
        $this->post(route('admin.posts.store'), $this->payload(['slug' => 'category']))->assertSessionHasErrors('slug');
        $this->post(route('admin.posts.store'), $this->payload(['slug' => 'taken']))->assertSessionHasErrors('slug');
        $this->post(route('admin.posts.store'), $this->payload(['slug' => 'Bad Slug']))->assertSessionHasErrors('slug');
    }

    public function test_changing_a_post_slug_redirects_the_old_url()
    {
        $post = Post::factory()->for($this->category)->create(['slug' => 'old-checklist']);

        $this->put(route('admin.posts.update', $post), $this->payload(['slug' => 'new-checklist']))->assertSessionHasNoErrors();

        $this->assertTrue(Redirect::where('from_path', '/blog/old-checklist')->where('to_path', '/blog/new-checklist')->exists());
    }

    public function test_seo_fields_are_saved()
    {
        $this->post(route('admin.posts.store'), $this->payload([
            'seo' => ['meta_title' => 'Moving Checklist 2026', 'meta_description' => 'Every step.', 'robots_index' => true, 'robots_follow' => true],
        ]));

        $this->assertSame('Moving Checklist 2026', Post::sole()->seo->meta_title);
    }

    public function test_index_filters_by_state()
    {
        Post::factory()->for($this->category)->create(['title' => 'Live one']);
        Post::factory()->for($this->category)->scheduled()->create(['title' => 'Later one']);
        Post::factory()->for($this->category)->draft()->create(['title' => 'Draft one']);

        $this->get(route('admin.posts.index', ['state' => 'scheduled']))->assertInertia(fn (Assert $page) => $page
            ->component('posts/Index')
            ->has('posts.data', 1)
            ->where('posts.data.0.title', 'Later one')
            ->where('posts.data.0.state', 'scheduled')
            ->where('counts', ['all' => 3, 'published' => 1, 'scheduled' => 1]));

        $this->get(route('admin.posts.index', ['state' => 'draft']))->assertInertia(fn (Assert $page) => $page
            ->has('posts.data', 1)->where('posts.data.0.title', 'Draft one'));
    }

    public function test_featured_toggle_and_delete()
    {
        $post = Post::factory()->for($this->category)->create();

        $this->patch(route('admin.posts.featured', $post));
        $this->assertTrue($post->fresh()->is_featured);

        $this->delete(route('admin.posts.destroy', $post))->assertRedirect(route('admin.posts.index'));
        $this->assertSoftDeleted($post);
    }

    public function test_preview_shows_a_draft_to_admins_with_noindex()
    {
        $post = Post::factory()->for($this->category)->draft()->create(['title' => 'Secret draft']);

        $this->get(route('admin.posts.preview', $post))
            ->assertOk()
            ->assertSee('Secret draft')
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false);

        auth()->logout();
        $this->get(route('admin.posts.preview', $post))->assertRedirect();
    }

    public function test_images_inside_articles_count_as_media_usage()
    {
        $media = Media::factory()->create();
        Post::factory()->for($this->category)->create([
            'title' => 'Packing guide',
            'content' => '<img src="/x.webp" alt="Boxes" data-media-id="'.$media->id.'">',
        ]);

        $this->assertContains(
            ['label' => 'Post: Packing guide', 'field' => 'Image in the article'],
            app(MediaUsage::class)->find($media),
        );
    }

    public function test_categories_crud_with_seo_and_guarded_delete()
    {
        $this->post(route('admin.blog-categories.store'), [
            'name' => 'Storage Guides',
            'slug' => 'storage-guides',
            'description' => 'All about storage.',
            'seo' => ['meta_title' => 'Storage Guides', 'robots_index' => true, 'robots_follow' => true],
        ])->assertSessionHasNoErrors();

        $category = Category::where('slug', 'storage-guides')->sole();
        $this->assertSame('Storage Guides', $category->seo->meta_title);
        $this->assertSame(3, $category->sort);

        Post::factory()->for($category)->create();
        $this->delete(route('admin.blog-categories.destroy', $category));
        $this->assertNotSoftDeleted($category);

        $this->put(route('admin.blog-categories.update', $category), [
            'name' => 'Storage', 'slug' => 'storage', 'description' => null, 'seo' => [],
        ])->assertSessionHasNoErrors();
        $this->assertTrue(Redirect::where('from_path', '/blog/category/storage-guides')->exists());
    }

    public function test_authors_crud()
    {
        $this->post(route('admin.authors.store'), [
            'name' => 'Maria Lopez', 'slug' => 'maria-lopez', 'job_title' => 'Move Coordinator', 'bio' => 'Ten years of moves.', 'photo_media_id' => null,
        ])->assertSessionHasNoErrors();

        $author = Author::sole();
        $post = Post::factory()->for($this->category)->for($author)->create();

        $this->delete(route('admin.authors.destroy', $author));
        $this->assertNull($post->fresh()->author_id);
    }

    public function test_tags_can_be_renamed_and_names_stay_unique()
    {
        $a = Tag::create(['name' => 'Budget', 'slug' => 'budget']);
        Tag::create(['name' => 'Packing', 'slug' => 'packing']);

        $this->put(route('admin.tags.update', $a), ['name' => 'packing'])->assertSessionHasErrors('name');
        $this->put(route('admin.tags.update', $a), ['name' => 'Moving Budget'])->assertSessionHasNoErrors();
        $this->assertSame('moving-budget', $a->fresh()->slug);
    }
}
