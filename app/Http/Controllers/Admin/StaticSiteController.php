<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StaticBuild;
use App\Services\Static\StaticPublisher;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/** "Rebuild site" on the dashboard, and the status it shows. */
class StaticSiteController extends Controller
{
    public function rebuild(StaticPublisher $publisher): RedirectResponse
    {
        if (! $publisher->enabled()) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => 'The static cache is switched off in Settings → Static Cache. Pages are rendered live.']);

            return back();
        }

        if ($publisher->runningBatch()) {
            Inertia::flash('toast', ['type' => 'info', 'message' => 'A rebuild is already running.']);

            return back();
        }

        $count = $publisher->buildAll();
        Inertia::flash('toast', ['type' => 'success', 'message' => "Site rebuilding… ({$count} pages)"]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    public static function status(StaticPublisher $publisher): array
    {
        $batch = $publisher->runningBatch();
        $last = StaticBuild::query()->latest('built_at')->latest('id')->first();

        return [
            'enabled' => $publisher->enabled(),
            'files' => $publisher->fileCount(),
            'running' => $batch ? [
                'total' => $batch->totalJobs,
                'done' => $batch->processedJobs(),
                'progress' => $batch->progress(),
            ] : null,
            'lastBuiltAt' => $last?->built_at?->toIso8601String(),
            // Pages that failed in the last day (latest result per page).
            'failures' => StaticBuild::query()
                ->where('built_at', '>=', now()->subDay())
                ->whereIn('id', StaticBuild::query()->selectRaw('max(id)')->groupBy('path'))
                ->where('status', 'failed')
                ->orderByDesc('built_at')
                ->limit(10)
                ->get(['path', 'error', 'built_at'])
                ->map(fn (StaticBuild $b) => ['path' => $b->path, 'error' => $b->error]),
        ];
    }
}
