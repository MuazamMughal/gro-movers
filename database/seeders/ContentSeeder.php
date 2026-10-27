<?php

namespace Database\Seeders;

use App\Blog\PostContent;
use App\Enums\MenuLinkType;
use App\Enums\MenuLocation;
use App\Enums\PageStatus;
use App\Enums\PostStatus;
use App\Enums\ReviewSource;
use App\Enums\SystemPage;
use App\Models\Area;
use App\Models\AreaServicePage;
use App\Models\Author;
use App\Models\Category;
use App\Models\Faq;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Post;
use App\Models\Review;
use App\Models\Service;
use App\Models\Tag;
use App\Sections\SectionSync;
use App\Services\Static\StaticPublisher;
use App\Support\Settings;
use Database\Seeders\Content\PlaceholderMedia;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Once;
use Illuminate\Support\Str;

/**
 * SEO-ready starter content (spec §12): 6 services, 3 cities with all 18 city ×
 * service pages, the FAQ bank, 12 sample reviews, every system page, menus,
 * 1 author, 9 blog posts and generated placeholder images.
 *
 * Runs once: if any service exists the content is considered installed and
 * nothing is touched, so admin edits are never overwritten. Copy lives in
 * database/seeders/content/*.php.
 */
class ContentSeeder extends Seeder
{
    private PlaceholderMedia $images;

    private SectionSync $sections;

    /** @var array<string, Faq> global FAQ bank by position */
    private array $faqBank = [];

    public function run(): void
    {
        if (Service::query()->exists()) {
            $this->command?->warn('Starter content skipped: the site already has services.');

            return;
        }

        $auto = config('static.auto');
        config(['static.auto' => false]); // one full rebuild at the end instead of hundreds of small ones

        $this->images = app(PlaceholderMedia::class);
        $this->sections = app(SectionSync::class);

        $areas = require database_path('seeders/content/areas.php');

        DB::transaction(function () use ($areas) {
            $this->defaultShareImage();
            $this->faqBank($areas['faqs']);
            $services = $this->services(require database_path('seeders/content/services.php'));
            $cities = $this->areas($areas['areas'], $services);
            $this->areaServices($areas['template'], $cities, $services);
            $this->reviews($areas['reviews'], $cities, $services);
            $this->pages(require database_path('seeders/content/pages.php'));
            $this->blog(require database_path('seeders/content/posts.php'));
            $this->menus($services, $cities);
        });

        config(['static.auto' => $auto]);
        Once::flush();
        Settings::flush();

        if ($auto && app(StaticPublisher::class)->enabled()) {
            $count = app(StaticPublisher::class)->buildAll();
            $this->command?->info("Starter content installed. Static rebuild of {$count} pages queued.");
        } else {
            $this->command?->info('Starter content installed.');
        }
    }

    private function defaultShareImage(): void
    {
        if (! Settings::get('seo.default_og_image_id')) {
            $media = $this->images->make('share-default', 'blog', 'truck', 'Moving truck illustration in the brand colors');
            Settings::set('seo.default_og_image_id', $media->id);
        }
    }

    /** @param  list<array{0: string, 1: string}>  $faqs */
    private function faqBank(array $faqs): void
    {
        foreach ($faqs as $i => [$question, $answer]) {
            $this->faqBank[$i] = Faq::query()->create(['question' => $question, 'answer' => $answer, 'sort' => $i, 'is_active' => true]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $data
     * @return array<string, Service> by slug
     */
    private function services(array $data): array
    {
        $services = [];
        $palette = [PlaceholderMedia::NAVY, PlaceholderMedia::ORANGE];

        foreach ($data as $i => $item) {
            $hero = $this->images->make("{$item['slug']}-hero", 'hero', $item['icon'], "{$item['title']} illustration");
            $card = $this->images->make("{$item['slug']}-card", 'card', $item['icon'], "{$item['title']} by our moving crew", $palette[$i % 2]);

            $service = Service::query()->create([
                'title' => $item['title'],
                'slug' => $item['slug'],
                'short_description' => $item['short'],
                'image_media_id' => $card->id,
                'hero_media_id' => $hero->id,
                'sort' => $i,
                'is_active' => true,
                'show_in_menu' => true,
                'show_on_home' => true,
            ]);

            // No buttons set: the hero shows Get a Free Quote and Call Now.
            $heroSection = ['type' => 'hero', 'data' => [
                ...$item['hero'],
                'bullets' => array_map(fn ($text) => ['text' => $text], $item['hero']['bullets']),
                'background_image' => $hero->id,
                'theme' => 'dark',
            ]];

            $this->syncSections($service, [$heroSection, ...$item['sections']], ['@card' => $card->id, '@hero' => $hero->id]);
            $this->seo($service, $item['meta_title'], $item['meta_description']);
            $this->attachFaqs($service, $item['faqs']);

            $services[$item['slug']] = $service;
        }

        return $services;
    }

    /**
     * @param  list<array<string, mixed>>  $data
     * @param  array<string, Service>  $services
     * @return array<string, Area>
     */
    private function areas(array $data, array $services): array
    {
        $cities = [];

        foreach ($data as $i => $item) {
            $hero = $this->images->make("{$item['slug']}-hero", 'hero', $item['icon'], "Movers in {$item['city']}, {$item['state_code']}");
            $card = $this->images->make("{$item['slug']}-card", 'card', $item['icon'], "Moving in {$item['city']}, {$item['state_code']}", $i % 2 ? PlaceholderMedia::ORANGE : PlaceholderMedia::NAVY);

            $area = Area::query()->create([
                ...collect($item)->only(['city', 'state_name', 'state_code', 'slug', 'intro', 'lat', 'lng', 'zip_codes'])->all(),
                'hero_media_id' => $hero->id,
                'image_media_id' => $card->id,
                'sort' => $i,
                'is_active' => true,
                'show_in_menu' => true,
            ]);

            $area->services()->sync(collect($services)->pluck('id'));

            $this->syncSections($area, [['type' => 'rich_text', 'data' => [
                'heading' => "Moving in {$item['city']}",
                'content' => $item['content'],
                'width' => 'narrow',
                'background' => 'light',
            ]]]);
            $this->seo($area, $item['meta_title'], $item['meta_description']);
            // City-specific questions plus two general ones.
            $this->attachFaqs($area, $item['faqs'], extra: [$this->faqBank[2], $this->faqBank[14]]);

            $cities[$item['slug']] = $area;
        }

        // Every city lists the others as nearby.
        foreach ($cities as $area) {
            $area->neighbors()->sync(collect($cities)->reject(fn (Area $a) => $a->is($area))->pluck('id'));
        }

        return $cities;
    }

    /**
     * @param  array<string, Area>  $cities
     * @param  array<string, Service>  $services
     */
    private function areaServices(array $template, array $cities, array $services): void
    {
        $page = Page::system(SystemPage::AreaServiceTemplate);
        $this->syncSections($page, $template);

        foreach ($cities as $area) {
            foreach ($services as $service) {
                AreaServicePage::query()->updateOrCreate(
                    ['area_id' => $area->id, 'service_id' => $service->id],
                    ['is_active' => true, 'use_template' => true],
                );
            }
        }
    }

    /**
     * @param  array<string, Area>  $cities
     * @param  array<string, Service>  $services
     */
    private function reviews(array $data, array $cities, array $services): void
    {
        $sources = [ReviewSource::Google, ReviewSource::Google, ReviewSource::Yelp, ReviewSource::Facebook, ReviewSource::Website];

        foreach ($data as $i => $item) {
            Review::query()->create([
                'name' => $item['name'],
                'city' => $item['city'],
                'rating' => $item['rating'],
                'body' => $item['body'],
                'source' => $sources[$i % count($sources)],
                'reviewed_at' => now()->subDays(12 + $i * 17)->toDateString(),
                'area_id' => $cities[$item['area']]->id,
                'service_id' => $services[$item['service']]->id,
                'is_featured' => $item['featured'],
                'is_active' => true,
                'is_sample' => true,
                'sort' => $i,
            ]);
        }
    }

    /** @param  array<string, array<string, mixed>>  $data */
    private function pages(array $data): void
    {
        $hero = $this->images->make('home-hero', 'hero', 'truck', 'Moving truck on the road in Dallas–Fort Worth');
        $about = $this->images->make('about-team', 'card', 'person', 'Our moving team', PlaceholderMedia::ORANGE);

        foreach ($data as $key => $item) {
            $page = Page::system(SystemPage::from($key));

            if (! $page) {
                continue;
            }

            $page->update([
                'status' => PageStatus::Published,
                'published_at' => $page->published_at ?? now(),
                'admin_note' => $item['admin_note'] ?? null,
            ]);

            if ($item['sections'] !== []) {
                $this->syncSections($page, $item['sections'], ['@hero' => $hero->id, '@about' => $about->id]);
            }

            $this->seo($page, $item['meta_title'], $item['meta_description']);

            if (isset($item['faqs'])) {
                $page->faqs()->sync(collect($item['faqs'])->mapWithKeys(fn (int $i, int $sort) => [$this->faqBank[$i]->id => ['sort' => $sort]])->all());
            }
        }
    }

    private function blog(array $data): void
    {
        $photo = $this->images->make('author-'.$data['author']['slug'], 'square', 'person', $data['author']['name'], PlaceholderMedia::SKY, PlaceholderMedia::NAVY);
        $author = Author::query()->create([...$data['author'], 'photo_media_id' => $photo->id]);

        $tags = collect($data['tags'])->mapWithKeys(fn (string $name) => [$name => Tag::query()->firstOrCreate(['slug' => Str::slug($name)], ['name' => $name])]);

        $categorySeo = [
            'moving-tips' => ['Moving Tips From Professional Movers', 'Practical moving tips from our Dallas–Fort Worth crews: checklists, moving day advice and how to move with kids and pets.'],
            'packing-guides' => ['Packing Guides: How to Pack Every Room', 'Step-by-step packing guides from professional movers: kitchens, fragile items, supplies lists and how to label boxes.'],
            'moving-cost-guides' => ['Moving Cost Guides: What a Move Really Costs', 'Honest guides to moving costs in Dallas–Fort Worth and beyond: local and long-distance pricing, budgets and ways to save.'],
        ];

        foreach (Category::query()->get() as $category) {
            if (isset($categorySeo[$category->slug])) {
                $this->seo($category, ...$categorySeo[$category->slug]);
            }
        }

        $colors = [PlaceholderMedia::NAVY, PlaceholderMedia::ORANGE, PlaceholderMedia::MUTED];

        foreach ($data['posts'] as $i => $item) {
            $muted = $colors[$i % 3] === PlaceholderMedia::MUTED;
            $image = $this->images->make("post-{$item['slug']}", 'blog', $item['icon'], $item['title'], $colors[$i % 3], $muted ? PlaceholderMedia::NAVY : '#FFFFFF');

            $post = Post::query()->create([
                'category_id' => Category::query()->where('slug', $item['category'])->value('id'),
                'author_id' => $author->id,
                'title' => $item['title'],
                'slug' => $item['slug'],
                'excerpt' => $item['excerpt'],
                'content' => PostContent::sanitize($item['content']),
                'featured_media_id' => $image->id,
                'status' => PostStatus::Published,
                'published_at' => now()->subDays($item['days_ago'])->setTime(9, 0),
                'is_featured' => $item['featured'],
            ]);

            $post->tags()->sync($tags->only($item['tags'])->pluck('id'));
            $this->seo($post, $item['meta_title'], $item['meta_description']);
        }
    }

    /**
     * @param  array<string, Service>  $services
     * @param  array<string, Area>  $cities
     */
    private function menus(array $services, array $cities): void
    {
        $page = fn (SystemPage $key) => Page::system($key)?->id;
        $item = fn (string $label, MenuLinkType $type, ?int $id = null, ?string $url = null, ?string $auto = null) => [
            'label' => $label, 'link_type' => $type, 'linkable_id' => $id, 'url' => $url, 'auto_children' => $auto,
        ];

        $menus = [
            MenuLocation::Header->value => ['Main menu', [
                $item('About', MenuLinkType::Page, $page(SystemPage::About)),
                $item('Services', MenuLinkType::Page, $page(SystemPage::ServicesIndex), auto: 'services'),
                $item('Service Areas', MenuLinkType::Page, $page(SystemPage::AreasIndex), auto: 'areas'),
                $item('Reviews', MenuLinkType::Page, $page(SystemPage::ReviewsIndex)),
                $item('Blog', MenuLinkType::Page, $page(SystemPage::BlogIndex)),
                $item('Contact', MenuLinkType::Page, $page(SystemPage::Contact)),
            ]],
            MenuLocation::Footer1->value => ['Moving Services', collect($services)->map(fn (Service $s) => $item($s->title, MenuLinkType::Service, $s->id))->values()->all()],
            MenuLocation::Footer2->value => ['Service Areas', [
                ...collect($cities)->map(fn (Area $a) => $item("Movers in {$a->city}", MenuLinkType::Area, $a->id))->values()->all(),
                $item('All service areas', MenuLinkType::Page, $page(SystemPage::AreasIndex)),
            ]],
            MenuLocation::Footer3->value => ['Company', [
                $item('About Us', MenuLinkType::Page, $page(SystemPage::About)),
                $item('Reviews', MenuLinkType::Page, $page(SystemPage::ReviewsIndex)),
                $item('Moving Guides', MenuLinkType::Page, $page(SystemPage::BlogIndex)),
                $item('Contact Us', MenuLinkType::Page, $page(SystemPage::Contact)),
                $item('Get a Free Quote', MenuLinkType::Page, $page(SystemPage::Quote)),
            ]],
        ];

        foreach ($menus as $location => [$title, $items]) {
            $menu = Menu::query()->firstOrCreate(['location' => $location], ['title' => $title]);

            if ($menu->items()->exists()) {
                continue; // already arranged in admin
            }

            $menu->update(['title' => $title]);
            foreach ($items as $sort => $row) {
                $menu->items()->create([...$row, 'sort' => $sort]);
            }
        }
    }

    // ---- helpers ------------------------------------------------------------------------

    /**
     * Save sections through the same path as the admin (sanitized, media tracked).
     *
     * @param  list<array{type: string, data: array<string, mixed>}>  $sections
     * @param  array<string, int>  $media  placeholders such as "@card" => media ID
     */
    private function syncSections(Model $owner, array $sections, array $media = []): void
    {
        $replace = function ($value) use (&$replace, $media) {
            if (is_array($value)) {
                return array_map($replace, $value);
            }

            return is_string($value) && isset($media[$value]) ? $media[$value] : $value;
        };

        $this->sections->sync($owner, array_map(fn (array $s) => [
            'type' => $s['type'],
            'data' => $replace($s['data']),
            'is_visible' => true,
        ], $sections));
    }

    private function seo(Model $model, string $title, string $description): void
    {
        $model->seo()->updateOrCreate([], [
            'meta_title' => $title,
            'meta_description' => $description,
            'robots_index' => $model->seo?->robots_index ?? true,
            'robots_follow' => true,
        ]);
    }

    /**
     * Create these questions in the FAQ bank and attach them (plus $extra) in order.
     *
     * @param  list<array{0: string, 1: string}>  $faqs
     * @param  list<Faq>  $extra
     */
    private function attachFaqs(Model $model, array $faqs, array $extra = []): void
    {
        $sort = count($this->faqBank) + Faq::query()->count();
        $records = array_map(fn (array $f) => Faq::query()->create(['question' => $f[0], 'answer' => $f[1], 'sort' => $sort++, 'is_active' => true]), $faqs);

        $model->faqs()->sync(collect([...$records, ...$extra])->values()->mapWithKeys(fn (Faq $f, int $i) => [$f->id => ['sort' => $i]])->all());
    }
}
