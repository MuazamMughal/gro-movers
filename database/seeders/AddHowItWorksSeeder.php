<?php

namespace Database\Seeders;

use App\Enums\MenuLinkType;
use App\Enums\MenuLocation;
use App\Enums\SystemPage;
use App\Models\Faq;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Service;
use App\Sections\SectionSync;
use Illuminate\Support\Facades\DB;

/**
 * Adds the How It Works page and its menu links to a site that already has content,
 * which ContentSeeder refuses to touch. Run it once by hand:
 *
 *     php artisan db:seed --class=AddHowItWorksSeeder --force
 *
 * If the URL is taken (or the page was deleted in admin) nothing happens. Existing
 * menu items keep their order; the new link goes after the given neighbor.
 */
class AddHowItWorksSeeder extends ContentSeeder
{
    public function run(): void
    {
        $data = require database_path('seeders/Content/how-it-works.php');

        if (! Service::query()->exists()) {
            $this->command?->warn('No content yet: run ContentSeeder instead.');

            return;
        }

        if (Page::withTrashed()->where('slug', $data['slug'])->exists()) {
            $this->command?->info('The How It Works page already exists.');

            return;
        }

        $auto = config('static.auto');
        config(['static.auto' => false]);

        $this->sections = app(SectionSync::class);

        // The page attaches general FAQs from the bank by position.
        $bank = (require database_path('seeders/Content/areas.php'))['faqs'];

        foreach ($data['faqs'] as $position) {
            [$question, $answer] = $bank[$position];
            $this->faqBank[$position] = Faq::query()->firstOrCreate(['question' => $question], ['answer' => $answer, 'is_active' => true]);
        }

        DB::transaction(function () use ($data) {
            $page = $this->howItWorksPage($data);

            $this->addToMenu(MenuLocation::Header, $page, after: SystemPage::AreasIndex);
            $this->addToMenu(MenuLocation::Footer3, $page, after: SystemPage::About);
        });

        config(['static.auto' => $auto]);

        $this->finish($auto, 'Added the How It Works page.');
    }

    /** Insert among the top-level items, after the link to $after (or last if it is gone). */
    private function addToMenu(MenuLocation $location, Page $page, SystemPage $after): void
    {
        $menu = Menu::query()->where('location', $location->value)->first();
        $items = $menu?->items()->whereNull('parent_id')->orderBy('sort')->get();

        if (! $items || $items->isEmpty()) {
            return;
        }

        $neighbor = Page::system($after)?->id;
        $position = $items->search(fn ($item) => $item->link_type === MenuLinkType::Page && $item->linkable_id === $neighbor);
        $position = $position === false ? $items->count() : $position + 1;

        $link = $menu->items()->create(['label' => $page->title, 'link_type' => MenuLinkType::Page, 'linkable_id' => $page->id, 'sort' => $position]);

        $items->splice($position, 0, [$link]);
        $items->values()->each(fn ($item, int $sort) => $item->update(['sort' => $sort]));
    }
}
