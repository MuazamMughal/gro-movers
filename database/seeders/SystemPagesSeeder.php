<?php

namespace Database\Seeders;

use App\Enums\PageStatus;
use App\Enums\SystemPage;
use App\Models\Page;
use Illuminate\Database\Seeder;

class SystemPagesSeeder extends Seeder
{
    /**
     * Ensure every system page exists. Section content is seeded in T16.
     */
    public function run(): void
    {
        foreach (SystemPage::cases() as $key) {
            $page = Page::query()->firstOrCreate(
                ['system_key' => $key],
                [
                    'title' => $key->defaultTitle(),
                    'slug' => $key->defaultSlug(),
                    'status' => PageStatus::Published,
                    'published_at' => now(),
                ],
            );

            if ($page->seo !== null) {
                continue;
            }

            if ($key === SystemPage::ThankYou) {
                $page->seo()->create(['robots_index' => false]);
            }

            // The template's SEO is inherited (with tokens filled in) by every
            // "{service} in {city}" page, so it must stay indexable.
            if ($key === SystemPage::AreaServiceTemplate) {
                $page->seo()->create([
                    'meta_title' => '{service} in {city}, {state_code} | Free Quote',
                    'meta_description' => 'Looking for {service} in {city}, {state_code}? {company} provides licensed, insured movers with upfront pricing. Get your free quote today.',
                    'robots_index' => true,
                    'robots_follow' => true,
                ]);
            }
        }
    }
}
