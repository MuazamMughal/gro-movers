<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Lead;
use App\Seo\SeoAudit;
use App\Services\Static\StaticPublisher;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /** Each card is sent only when the user's role covers that module (T19). */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $leads = $user->hasPermission('leads.view');

        return Inertia::render('Dashboard', [
            'stats' => $leads ? $this->leadStats($user->hasPermission('messages')) : null,
            'chart' => $leads ? $this->chart() : null,
            'latestLeads' => $leads ? Lead::query()->latest()->limit(10)->get([
                'id', 'full_name', 'from_city', 'to_city', 'move_date', 'move_type_name', 'status', 'read_at', 'created_at',
            ]) : null,
            'staticSite' => fn () => $user->hasPermission('static') ? StaticSiteController::status(app(StaticPublisher::class)) : null,
            // The cron job drives emails, rebuilds, scheduled posts and backups: warn when it stops.
            'schedulerLastRun' => Cache::get('scheduler.last_run'),
            // "Pages hidden from Google (N)": should only ever be /thank-you.
            'hiddenFromGoogle' => $user->hasPermission('seo') ? collect(SeoAudit::run()['hidden'])->map(fn ($h) => [
                'path' => $h['path'], 'label' => $h['label'], 'edit_url' => $h['edit_url'], 'expected' => $h['expected'],
            ])->values() : null,
        ]);
    }

    /** @return array<string, int|null> */
    private function leadStats(bool $messages): array
    {
        $today = CarbonImmutable::today();

        return [
            'leadsToday' => Lead::query()->where('created_at', '>=', $today)->count(),
            'leads7' => Lead::query()->where('created_at', '>=', $today->subDays(6))->count(),
            'leads30' => Lead::query()->where('created_at', '>=', $today->subDays(29))->count(),
            'unreadLeads' => Lead::query()->unread()->count(),
            'unreadMessages' => $messages ? ContactMessage::query()->unread()->count() : null,
        ];
    }

    /** @return Collection<int, array{date: string, total: int}> */
    private function chart(): Collection
    {
        $since = CarbonImmutable::today()->subDays(29);

        $perDay = Lead::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        return collect(range(0, 29))->map(function (int $offset) use ($since, $perDay) {
            $day = $since->addDays($offset)->toDateString();

            return ['date' => $day, 'total' => (int) ($perDay[$day] ?? 0)];
        });
    }
}
