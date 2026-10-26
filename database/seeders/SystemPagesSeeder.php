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

            if (! $key->indexable() && $page->seo === null) {
                $page->seo()->create(['robots_index' => false]);
            }
        }
    }
}
