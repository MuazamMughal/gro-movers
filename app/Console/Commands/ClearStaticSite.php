<?php

namespace App\Console\Commands;

use App\Services\Static\StaticPublisher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('static:clear')]
#[Description('Delete every static HTML file: Laravel renders all pages live until the next build')]
class ClearStaticSite extends Command
{
    public function handle(StaticPublisher $publisher): int
    {
        $count = $publisher->fileCount();
        $publisher->purgeAll();
        $this->info("Removed {$count} static ".str('page')->plural($count).'.');

        return self::SUCCESS;
    }
}
