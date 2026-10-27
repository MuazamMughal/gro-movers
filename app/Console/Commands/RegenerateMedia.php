<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Services\Media\MediaService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * Re-create every image's responsive variants from its kept original, with its
 * saved preset and crop (after changing config/media.php widths or quality).
 */
#[Signature('media:regenerate {--id=* : Only these media IDs}')]
#[Description('Regenerate the responsive image variants from the originals')]
class RegenerateMedia extends Command
{
    public function handle(MediaService $media): int
    {
        $query = Media::query()->where('mime', '!=', 'image/svg+xml');

        if ($ids = $this->option('id')) {
            $query->whereKey($ids);
        }

        $failed = 0;

        $this->withProgressBar($query->get(), function (Media $item) use ($media, &$failed) {
            try {
                $media->recrop($item, $item->preset, $item->crop);
            } catch (Throwable $e) {
                $failed++;
                report($e);
            }
        });

        $this->newLine();
        $this->info('Variants regenerated.'.($failed ? " {$failed} failed (see the log)." : ''));
        $this->line('Run php artisan static:build to refresh the static pages.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
