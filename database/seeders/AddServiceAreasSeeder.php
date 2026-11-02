<?php

namespace Database\Seeders;

use App\Enums\MenuLinkType;
use App\Enums\MenuLocation;
use App\Enums\SystemPage;
use App\Models\Area;
use App\Models\Faq;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Service;
use App\Sections\SectionSync;
use Database\Seeders\Content\PlaceholderMedia;
use Illuminate\Support\Facades\DB;

/**
 * Adds cities from Content/areas.php to a site that already has content, which
 * ContentSeeder refuses to touch. Run it by hand after adding cities:
 *
 *     php artisan db:seed --class=AddServiceAreasSeeder --force
 *
 * Only creates what is missing: existing cities, the city × service template's layout
 * and menu order stay as they are, and a city deleted in admin is not brought back.
 */
class AddServiceAreasSeeder extends ContentSeeder
{
    /** Meta titles from when there were 3 cities; replaced only if never edited. */
    private const OLD_AREAS_TITLE = 'Service Areas | Movers in Dallas, Plano & Irving, TX';

    private const OLD_TEMPLATE_TITLE = '{service} in {city}, {state_code} | {company}';

    private const OLD_AREAS_FAQ = '<p>We serve Dallas, Plano, Irving and the wider Dallas–Fort Worth area, and we handle long-distance moves to destinations across the United States.</p>';

    public function run(): void
    {
        if (! Service::query()->exists()) {
            $this->command?->warn('No content yet: run ContentSeeder instead.');

            return;
        }

        $this->refreshGeneratedCopy();

        $content = require database_path('seeders/Content/areas.php');
        $existing = Area::withTrashed()->pluck('slug')->all();
        $missing = array_values(array_filter($content['areas'], fn (array $area) => ! in_array($area['slug'], $existing, true)));

        if ($missing === []) {
            $this->command?->info('No new service areas to add.');

            return;
        }

        $auto = config('static.auto');
        config(['static.auto' => false]);

        $this->images = app(PlaceholderMedia::class);
        $this->sections = app(SectionSync::class);

        // City pages attach two general FAQs from the bank by position.
        foreach ([2, 14] as $position) {
            [$question, $answer] = $content['faqs'][$position];
            $this->faqBank[$position] = Faq::query()->firstOrCreate(['question' => $question], ['answer' => $answer, 'is_active' => true]);

            if ($this->faqBank[$position]->answer === self::OLD_AREAS_FAQ) {
                $this->faqBank[$position]->update(['answer' => $answer]);
            }
        }

        DB::transaction(function () use ($content, $missing) {
            $sort = (int) Area::query()->max('sort') + 1;
            $services = Service::query()->active()->get()->keyBy('slug')->all();
            $cities = $this->areas(array_map(fn (array $area, int $i) => [...$area, 'sort' => $sort + $i], $missing, array_keys($missing)), $services);

            $this->linkNearby($content['areas']);
            $this->areaServicePages($cities, $services);
            $this->addToFooter($cities);
        });

        config(['static.auto' => $auto]);

        $this->finish($auto, 'Added '.count($missing).' service areas.');
    }

    /**
     * New cities go after the footer's existing city links, before "All service areas".
     *
     * @param  array<string, Area>  $cities
     */
    private function addToFooter(array $cities): void
    {
        $menu = Menu::query()->where('location', MenuLocation::Footer2->value)->first();
        $items = $menu?->items()->orderBy('sort')->get();

        if (! $items || $items->isEmpty()) {
            return;
        }

        [$areaLinks, $others] = $items->partition(fn ($item) => $item->link_type === MenuLinkType::Area);
        $sort = $areaLinks->count();

        foreach ($cities as $area) {
            $menu->items()->create(['label' => "Movers in {$area->city}", 'link_type' => MenuLinkType::Area, 'linkable_id' => $area->id, 'sort' => $sort++]);
        }

        foreach ($others as $item) {
            $item->update(['sort' => $sort++]);
        }
    }

    /** Starter copy that no longer fits 10 cities; each is replaced only if never edited. */
    private function refreshGeneratedCopy(): void
    {
        // Most cities have no reviews of their own, so the slider shows reviews from other cities.
        Page::system(SystemPage::AreaServiceTemplate)?->sections()->where('type', 'reviews_slider')->get()
            ->filter(fn ($section) => ($section->data['heading'] ?? null) === 'What our customers in {city} say')
            ->each(fn ($section) => $section->update(['data' => [...$section->data, 'heading' => 'What our customers say']]));

        // Longer city names pushed the shared title past 60 characters.
        $template = Page::system(SystemPage::AreaServiceTemplate)?->seo;

        if ($template?->meta_title === self::OLD_TEMPLATE_TITLE) {
            $template->update(['meta_title' => '{service} in {city}, {state_code} | Free Quote']);
        }

        $seo = Page::system(SystemPage::AreasIndex)?->seo;

        if ($seo?->meta_title === self::OLD_AREAS_TITLE) {
            $seo->update([
                'meta_title' => 'Service Areas | Movers Across Dallas–Fort Worth, TX',
                'meta_description' => 'We move homes and businesses in Dallas, Fort Worth, Plano, Arlington, Frisco and across DFW, plus long-distance moves nationwide. Find your city.',
            ]);
        }
    }
}
