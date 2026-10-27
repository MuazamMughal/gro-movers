<?php

namespace App\Console\Commands;

use App\Http\Controllers\Site\SeoFileController;
use App\Services\Static\StaticPublisher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 *   php artisan static:build                 # queue a full rebuild (chunked jobs)
 *   php artisan static:build --now           # build every page in this process (deploys, cron)
 *   php artisan static:build /about-us /blog # just these pages, now
 */
#[Signature('static:build {paths?* : Paths to build now (default: the whole site)} {--now : Build in this process instead of queuing}')]
#[Description('Rebuild the static HTML cache (public/static)')]
class BuildStaticSite extends Command
{
    public function handle(StaticPublisher $publisher): int
    {
        if (! $publisher->enabled()) {
            $publisher->purgeAll();
            $this->warn('The static cache is switched off (Settings → Static Cache). Removed all static files.');

            return self::SUCCESS;
        }

        if (app()->isDownForMaintenance()) {
            $this->error('The site is in maintenance mode, so every page would answer 503. Run php artisan up first.');

            return self::FAILURE;
        }

        $paths = $this->argument('paths');

        if ($paths) {
            $this->report($publisher->buildMany($paths));

            return self::SUCCESS;
        }

        if (! $this->option('now')) {
            $count = $publisher->buildAll();
            $this->info("Queued a rebuild of {$count} pages. The queue worker builds them in the background.");

            return self::SUCCESS;
        }

        $all = $publisher->paths();
        $counts = ['ok' => 0, 'removed' => 0, 'failed' => 0];

        $this->withProgressBar($all, function (string $path) use ($publisher, &$counts) {
            $counts[$publisher->build($path)]++;
        });
        $this->newLine();

        $removed = $publisher->purgeOrphans($all);
        SeoFileController::flushSitemap();

        $this->report($counts);
        if ($removed) {
            $this->line("Removed {$removed} old ".str('file')->plural($removed).'.');
        }

        return $counts['failed'] ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array{ok: int, removed: int, failed: int}  $counts
     */
    private function report(array $counts): void
    {
        $this->info("Built {$counts['ok']} ".str('page')->plural($counts['ok']).'.');

        if ($counts['removed']) {
            $this->line("{$counts['removed']} not found or redirected: their files were removed.");
        }

        if ($counts['failed']) {
            $this->error("{$counts['failed']} failed. See the dashboard or storage/logs/laravel.log.");
        }
    }
}
