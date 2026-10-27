<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Lead;
use App\Seo\SeoAudit;
use App\Services\Static\StaticPublisher;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $today = CarbonImmutable::today();
        $since = $today->subDays(29);

        $perDay = Lead::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $chart = collect(range(0, 29))->map(function (int $offset) use ($since, $perDay) {
            $day = $since->addDays($offset)->toDateString();

            return ['date' => $day, 'total' => (int) ($perDay[$day] ?? 0)];
        });

        return Inertia::render('Dashboard', [
            'stats' => [
                'leadsToday' => Lead::query()->where('created_at', '>=', $today)->count(),
                'leads7' => Lead::query()->where('created_at', '>=', $today->subDays(6))->count(),
                'leads30' => Lead::query()->where('created_at', '>=', $since)->count(),
                'unreadLeads' => Lead::query()->unread()->count(),
                'unreadMessages' => ContactMessage::query()->unread()->count(),
            ],
            'chart' => $chart,
            'latestLeads' => Lead::query()->latest()->limit(10)->get([
                'id', 'full_name', 'from_city', 'to_city', 'move_date', 'move_type_name', 'status', 'read_at', 'created_at',
            ]),
            'staticSite' => fn () => StaticSiteController::status(app(StaticPublisher::class)),
            // "Pages hidden from Google (N)": should only ever be /thank-you.
            'hiddenFromGoogle' => collect(SeoAudit::run()['hidden'])->map(fn ($h) => [
                'path' => $h['path'], 'label' => $h['label'], 'edit_url' => $h['edit_url'], 'expected' => $h['expected'],
            ])->values(),
        ]);
    }
}
