<?php

use App\Models\StaticBuild;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduled blog posts: flip them to "published" when their time comes (static rebuild hooks in from T14).
// A crashed run releases its lock after 5 minutes (the default is 24 hours).
Schedule::command('blog:publish-scheduled')->everyMinute()->withoutOverlapping(5);

// Static build log: keep a week.
Schedule::call(fn () => StaticBuild::query()->where('built_at', '<', now()->subDays(7))->delete())->daily()->name('static-builds:prune');

// ---- Shared hosting (cPanel): one cron line runs everything below every minute. ----

// Queued jobs (lead emails, static rebuilds). cPanel has no process manager, so a
// short-lived worker starts each minute and exits when the queue is empty.
// Sail runs its own worker container: QUEUE_CRON_WORKER=false there.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3 --timeout=55')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->when(fn () => (bool) config('queue.cron_worker'))
    ->name('queue:cron-worker');

// Heartbeat: the dashboard and deploy:check warn when cron stops running.
Schedule::call(fn () => Cache::forever('scheduler.last_run', now()->toIso8601String()))->everyMinute()->name('scheduler:heartbeat');

// Daily backup: database dump + media zip in storage/app/backups (newest 7 kept).
Schedule::command('backup:run')->dailyAt('03:30')->withoutOverlapping()->name('backup:run');
