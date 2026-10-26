<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Lead;
use App\Models\StaticBuild;
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

        $lastBuild = StaticBuild::query()->latest('built_at')->first();

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
            'lastBuild' => $lastBuild?->only(['path', 'status', 'error', 'built_at']),
        ]);
    }
}
