<?php

namespace App\Jobs;

use App\Services\Static\StaticPublisher;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** One chunk of a full static rebuild (see StaticPublisher::buildAll()). */
class BuildStaticPages implements ShouldQueue
{
    use Batchable, Queueable;

    public int $timeout = 300;

    /**
     * @param  list<string>  $paths
     */
    public function __construct(public array $paths) {}

    public function handle(StaticPublisher $publisher): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $publisher->buildMany($this->paths);
    }
}
