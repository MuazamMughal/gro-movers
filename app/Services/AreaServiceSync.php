<?php

namespace App\Services;

use App\Models\Area;
use App\Models\AreaServicePage;

/**
 * Keeps "{service} in {city}" pages in step with the services an area offers.
 *
 * - Newly offered service: its page is created (or switched back on).
 * - Still offered: page left exactly as the admin set it in the grid.
 * - No longer offered: page switched off but kept, so custom content survives.
 */
class AreaServiceSync
{
    /**
     * @param  list<int>  $previouslyOffered  service IDs offered before the change
     */
    public function forArea(Area $area, array $previouslyOffered = []): void
    {
        $offered = $area->services()->pluck('services.id')->map(fn ($id) => (int) $id)->all();
        $added = array_diff($offered, $previouslyOffered);
        $removed = array_diff($previouslyOffered, $offered);

        foreach ($offered as $serviceId) {
            $page = AreaServicePage::query()->firstOrCreate(
                ['area_id' => $area->id, 'service_id' => $serviceId],
                ['is_active' => true, 'use_template' => true],
            );

            if (in_array($serviceId, $added, true) && ! $page->is_active) {
                $page->update(['is_active' => true]);
            }
        }

        if ($removed !== []) {
            AreaServicePage::query()
                ->where('area_id', $area->id)
                ->whereIn('service_id', $removed)
                ->update(['is_active' => false]);
        }
    }
}
