<?php

use App\Models\StaticBuild;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Every task runs inside the scheduler's own process (Schedule::call + Artisan::call)
 * instead of Schedule::command, which starts a child process through proc_open.
 * Shared hosts such as Hostinger disable proc_open, so a command task would never run.
 */
$artisan = fn (string $command) => fn () => Artisan::call($command);

// Scheduled blog posts: flip them to "published" when their time comes (static rebuild hooks in from T14).
// A crashed run releases its lock after 5 minutes (the default is 24 hours).
Schedule::call($artisan('blog:publish-scheduled'))->name('blog:publish-scheduled')->everyMinute()->withoutOverlapping(5);

// Static build log: keep a week.
Schedule::call(fn () => StaticBuild::query()->where('built_at', '<', now()->subDays(7))->delete())->daily()->name('static-builds:prune');

// ---- Shared hosting (cPanel, Hostinger): one cron line runs everything below every minute. ----

// Heartbeat: the dashboard and deploy:check warn when cron stops running.
// Before the worker, which can hold the run for up to 50 seconds.
Schedule::call(fn () => Cache::forever('scheduler.last_run', now()->toIso8601String()))->everyMinute()->name('scheduler:heartbeat');

// Queued jobs (lead emails, static rebuilds). Shared hosting has no process manager, so a
// short-lived worker starts each minute and exits when the queue is empty.
// Sail runs its own worker container: QUEUE_CRON_WORKER=false there.
// A job that hangs past --timeout ends this whole scheduler run; the next minute starts fresh.
Schedule::call($artisan('queue:work --stop-when-empty --max-time=50 --tries=3 --timeout=55'))
    ->name('queue:cron-worker')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->when(fn () => (bool) config('queue.cron_worker'));

// Daily backup: database dump + media zip in storage/app/backups (newest 7 kept).
Schedule::call($artisan('backup:run'))->name('backup:run')->dailyAt('03:30')->withoutOverlapping();
