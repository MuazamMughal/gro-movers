<?php

namespace App\Models\Concerns;

use App\Models\Redirect;

/**
 * Creates a 301 from the old public URL to the new one whenever the slug changes.
 */
trait HasSlugRedirect
{
    public static function bootHasSlugRedirect(): void
    {
        static::updated(function (self $model) {
            if (! $model->wasChanged('slug')) {
                return;
            }

            foreach ($model->slugRedirectPairs($model->getOriginal('slug'), $model->slug) as [$from, $to]) {
                Redirect::point($from, $to);
            }
        });
    }

    /** Public path the record would have with the given slug, or null if it has none. */
    abstract public function pathForSlug(string $slug): ?string;

    /**
     * Old → new path pairs to redirect. Models whose slug is part of other
     * records' URLs (areas, services) add those paths too.
     *
     * @return list<array{0: string, 1: string}>
     */
    protected function slugRedirectPairs(string $oldSlug, string $newSlug): array
    {
        $from = $this->pathForSlug($oldSlug);
        $to = $this->pathForSlug($newSlug);

        return $from !== null && $to !== null && $from !== $to ? [[$from, $to]] : [];
    }
}
