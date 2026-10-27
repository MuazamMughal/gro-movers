<?php

namespace App\Jobs;

use App\Http\Controllers\Site\SeoFileController;
use App\Services\Static\StaticPublisher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Rebuild the pages a content change affects (see StaticInvalidator), or the
 * whole site when the change shows on every page (menus, settings, theme…).
 */
class RebuildPaths implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 3;

    /**
     * @param  list<string>  $paths
     */
    public function __construct(public array $paths, public bool $full = false) {}

    public function handle(StaticPublisher $publisher): void
    {
        if ($this->full) {
            $publisher->buildAll();

            return;
        }

        $publisher->buildMany($this->paths);
        SeoFileController::flushSitemap();
    }
}
