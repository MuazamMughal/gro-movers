<?php

namespace Tests\Feature\Admin;

use App\Enums\ReviewSource;
use App\Enums\SystemPage;
use App\Models\Area;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Review;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SystemPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReviewsFaqsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([SettingsSeeder::class, SystemPagesSeeder::class]);
        $this->actingAs(User::factory()->create());
    }

    private function review(array $overrides = []): array
    {
        return [
            'name' => 'Jessica M.',
            'city' => 'Plano, TX',
            'rating' => 5,
            'body' => 'Fast, careful and friendly crew.',
            'source' => 'google',
            'reviewed_at' => '2026-09-01',
            'photo_media_id' => null,
            'area_id' => null,
            'service_id' => null,
            'is_featured' => true,
            'is_active' => true,
            ...$overrides,
        ];
    }

    public function test_reviews_can_be_created_with_area_and_service_links()
    {
        $area = Area::factory()->create();
        $service = Service::factory()->create();

        $this->post(route('admin.reviews.store'), $this->review(['area_id' => $area->id, 'service_id' => $service->id]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.reviews.index'));

        $review = Review::sole();
        $this->assertSame(ReviewSource::Google, $review->source);
        $this->assertTrue($review->area->is($area));
        $this->assertTrue($review->service->is($service));
        $this->assertTrue($review->is_featured);
    }

    public function test_review_validation()
    {
        $this->post(route('admin.reviews.store'), $this->review(['rating' => 6]))->assertSessionHasErrors('rating');
        $this->post(route('admin.reviews.store'), $this->review(['rating' => 0]))->assertSessionHasErrors('rating');
        $this->post(route('admin.reviews.store'), $this->review(['source' => 'tripadvisor']))->assertSessionHasErrors('source');
        $this->post(route('admin.reviews.store'), $this->review(['body' => '']))->assertSessionHasErrors('body');
        $this->post(route('admin.reviews.store'), $this->review(['reviewed_at' => now()->addDay()->format('Y-m-d')]))->assertSessionHasErrors('reviewed_at');
    }

    public function test_review_list_filters_toggles_and_delete()
    {
        $google = Review::factory()->create(['source' => 'google', 'rating' => 5, 'is_active' => true]);
        Review::factory()->create(['source' => 'yelp', 'rating' => 3, 'is_active' => true]);

        $this->get(route('admin.reviews.index', ['source' => 'google']))->assertInertia(fn (Assert $page) => $page
            ->component('reviews/Index')
            ->has('reviews.data', 1)
            ->where('reviews.data.0.id', $google->id)
            ->where('stats', ['samples' => 0, 'count' => 2, 'average' => 4]));

        $this->patch(route('admin.reviews.toggle', $google), ['field' => 'is_active']);
        $this->assertFalse($google->fresh()->is_active);
        $this->patch(route('admin.reviews.toggle', $google), ['field' => 'rating'])->assertSessionHasErrors('field');

        $this->delete(route('admin.reviews.destroy', $google));
        $this->assertModelMissing($google);
    }

    public function test_faq_answers_are_sanitized_and_ordered()
    {
        $this->post(route('admin.faqs.store'), [
            'question' => 'Are you insured?',
            'answer' => '<p>Yes, <strong>fully</strong>.</p><script>alert(1)</script>',
            'is_active' => true,
        ])->assertSessionHasNoErrors();

        $faq = Faq::sole();
        $this->assertSame('<p>Yes, <strong>fully</strong>.</p>', $faq->answer);

        $this->post(route('admin.faqs.store'), ['question' => 'Empty?', 'answer' => '<p> </p>', 'is_active' => true])
            ->assertSessionHasErrors('answer');

        $second = Faq::factory()->create(['sort' => 5]);
        $this->post(route('admin.faqs.reorder'), ['ids' => [$second->id, $faq->id]]);
        $this->assertSame([$second->id, $faq->id], Faq::ordered()->pluck('id')->all());
    }

    public function test_faq_usages_list_attached_records_and_picked_sections()
    {
        $faq = Faq::factory()->create();
        $service = Service::factory()->create(['title' => 'Local Moving']);
        $service->faqs()->attach($faq, ['sort' => 0]);
        $about = Page::system(SystemPage::About);
        $about->sections()->create(['type' => 'faq', 'data' => ['mode' => 'picked', 'faqs' => [$faq->id]], 'is_visible' => true, 'sort' => 0]);

        $this->getJson(route('admin.faqs.show', $faq))
            ->assertOk()
            ->assertJsonFragment(['label' => 'Service: Local Moving', 'path' => '/services/'.$service->slug])
            ->assertJsonFragment(['label' => 'Page: '.$about->title.' (FAQ section)']);

        $this->get(route('admin.faqs.index'))->assertInertia(fn (Assert $page) => $page->where('faqs.0.attached_count', 1));

        $this->delete(route('admin.faqs.destroy', $faq));
        $this->assertSame(0, $service->faqs()->count());
    }

    public function test_pages_can_attach_faqs_and_show_them_automatically()
    {
        $faq = Faq::factory()->create(['question' => 'Do you move pianos?', 'answer' => '<p>Yes.</p>']);
        $about = Page::system(SystemPage::About);

        $this->put(route('admin.pages.update', $about), [
            'title' => $about->title,
            'slug' => $about->slug,
            'status' => 'published',
            'faq_ids' => [$faq->id],
            'seo' => ['robots_index' => true, 'robots_follow' => true],
            'sections' => [],
        ])->assertSessionHasNoErrors();

        $this->assertSame([$faq->id], $about->faqs()->pluck('faqs.id')->all());
        $this->get($about->publicPath())->assertOk()->assertSee('Do you move pianos?');
    }

    public function test_public_reviews_page_shows_rating_summary_and_active_reviews_only()
    {
        Review::factory()->create(['name' => 'Ana R.', 'rating' => 5, 'source' => 'yelp', 'is_active' => true]);
        Review::factory()->create(['name' => 'Ben T.', 'rating' => 4, 'is_active' => true]);
        Review::factory()->create(['name' => 'Hidden H.', 'is_active' => false]);

        auth()->logout();

        $this->get('/reviews')
            ->assertOk()
            ->assertSee('4.5')
            ->assertSee('Based on 2 reviews')
            ->assertSee('Ana R.')
            ->assertSee('Yelp')
            ->assertDontSee('Hidden H.');
    }
}
