<?php

use App\Models\StaticBuild;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduled blog posts: flip them to "published" when their time comes (static rebuild hooks in from T14).
Schedule::command('blog:publish-scheduled')->everyMinute()->withoutOverlapping();

// Static build log: keep a week.
Schedule::call(fn () => StaticBuild::query()->where('built_at', '<', now()->subDays(7))->delete())->daily()->name('static-builds:prune');
