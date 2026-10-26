<?php

namespace Tests\Feature\Models;

use App\Enums\SystemPage;
use App\Models\Area;
use App\Models\AreaServicePage;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Post;
use App\Models\Section;
use App\Models\Service;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_seo_falls_back_to_title_with_suffix_and_trimmed_description()
    {
        Settings::set('seo.title_suffix', '| Acme Movers');
        $service = Service::factory()->create([
            'title' => 'Local Moving',
            'short_description' => '<p>'.str_repeat('Fast and careful local movers. ', 12).'</p>',
        ]);

        $this->assertSame('Local Moving | Acme Movers', $service->seoTitle());
        $this->assertLessThanOrEqual(156, mb_strlen($service->seoDescription()));
        $this->assertStringNotContainsString('<p>', $service->seoDescription());
        $this->assertTrue($service->seoUsesFallbacks());
    }

    public function test_custom_seo_values_win_over_fallbacks()
    {
        $service = Service::factory()->create();
        $service->seo()->create([
            'meta_title' => 'Custom Title',
            'meta_description' => 'Custom description.',
            'robots_index' => false,
        ]);
        $service->load('seo');

        $this->assertSame('Custom Title', $service->seoTitle());
        $this->assertSame('Custom description.', $service->seoDescription());
        $this->assertFalse($service->seoIndexable());
        $this->assertFalse($service->seoUsesFallbacks());
    }

    public function test_area_and_area_service_page_titles_and_paths()
    {
        $area = Area::factory()->create(['city' => 'Irving', 'state_code' => 'TX', 'slug' => 'irving-tx']);
        $service = Service::factory()->create(['title' => 'Packing Services', 'slug' => 'packing-services']);
        $page = AreaServicePage::factory()->for($area)->for($service)->create();

        $this->assertSame('Irving, TX', $area->name);
        $this->assertSame('/service-areas/irving-tx', $area->publicPath());
        $this->assertSame('/service-areas/irving-tx/packing-services', $page->publicPath());
        $this->assertStringStartsWith('Packing Services in Irving, TX', $page->seoTitle());
    }

    public function test_system_page_paths()
    {
        $home = Page::factory()->create(['system_key' => SystemPage::Home, 'slug' => 'home']);
        $about = Page::factory()->create(['system_key' => SystemPage::About, 'slug' => 'about-us']);
        $template = Page::factory()->create(['system_key' => SystemPage::AreaServiceTemplate, 'slug' => 'tpl']);

        $this->assertSame('/', $home->publicPath());
        $this->assertSame('/about-us', $about->publicPath());
        $this->assertNull($template->publicPath());
    }

    public function test_post_reading_time_and_published_scope()
    {
        $live = Post::factory()->create(['content' => str_repeat('word ', 450)]);
        $dueScheduled = Post::factory()->scheduled(now()->subMinute())->create();
        Post::factory()->scheduled(now()->addHour())->create();
        Post::factory()->draft()->create();

        $this->assertSame(3, $live->reading_minutes);
        $this->assertEqualsCanonicalizing(
            [$live->id, $dueScheduled->id],
            Post::published()->pluck('id')->all(),
        );
    }

    public function test_polymorphic_relations_use_the_morph_map_and_clean_up_on_force_delete()
    {
        $service = Service::factory()->create();
        $service->sections()->create(['type' => 'hero', 'data' => ['heading' => 'Hi'], 'sort' => 1]);
        $service->sections()->create(['type' => 'faq', 'sort' => 0, 'is_visible' => false]);
        $service->seo()->create(['meta_title' => 'X']);
        $service->faqs()->attach(Faq::factory()->create(), ['sort' => 0]);

        $this->assertDatabaseHas('sections', ['sectionable_type' => 'service', 'sectionable_id' => $service->id]);
        $this->assertSame(['faq', 'hero'], $service->sections->pluck('type')->all());
        $this->assertSame(['hero'], $service->visibleSections->pluck('type')->all());

        $service->delete();
        $this->assertSame(2, Section::count(), 'soft delete keeps sections for restore');

        $service->forceDelete();
        $this->assertSame(0, Section::count());
        $this->assertDatabaseCount('seo_meta', 0);
        $this->assertDatabaseCount('faqables', 0);
    }

    public function test_settings_are_cached_and_flushed_on_save()
    {
        Settings::set('theme.tertiary', '#FF0000');
        $this->assertSame('#FF0000', Settings::get('theme.tertiary'));

        Settings::set('theme.tertiary', '#00FF00');
        $this->assertSame('#00FF00', Settings::get('theme.tertiary'));
        $this->assertSame(['tertiary' => '#00FF00'], Settings::group('theme'));
    }
}
