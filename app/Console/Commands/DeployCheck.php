<?php

namespace App\Console\Commands;

use App\Services\Static\StaticPublisher;
use App\Support\Settings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Checks a server against everything the site needs (docs/deploy-cpanel.md).
 * Read-only: it changes nothing. Exit code 1 when a required check fails.
 */
#[Signature('deploy:check')]
#[Description('Check that this server is ready to run the site')]
class DeployCheck extends Command
{
    public const EXTENSIONS = ['gd', 'pdo_mysql', 'mbstring', 'intl', 'fileinfo', 'zip', 'exif', 'openssl', 'zlib'];

    private int $failed = 0;

    private int $warnings = 0;

    public function handle(StaticPublisher $publisher): int
    {
        $production = app()->isProduction();

        $this->section('PHP');
        $this->check('PHP 8.3 or newer', version_compare(PHP_VERSION, '8.3.0', '>='), PHP_VERSION);
        foreach (self::EXTENSIONS as $ext) {
            $this->check("Extension: {$ext}", extension_loaded($ext));
        }
        $this->check('GD can write WebP', function_exists('imagewebp'), fix: 'needed for responsive images');

        $this->section('Environment (.env)');
        $this->check('APP_KEY is set', filled(config('app.key')), fix: 'run: php artisan key:generate');
        $this->check('APP_ENV is production', $production, (string) config('app.env'), required: false);
        $this->check('APP_DEBUG is off', ! config('app.debug'), fix: 'APP_DEBUG=false', required: $production);
        $this->check('APP_URL uses https', str_starts_with((string) config('app.url'), 'https://'), (string) config('app.url'), required: $production);
        $this->check('Queue driver is database', config('queue.default') === 'database', (string) config('queue.default'));
        $this->check('Queue worker runs from cron', (bool) config('queue.cron_worker'), fix: 'QUEUE_CRON_WORKER=true on cPanel', required: false);
        $mailHost = (string) config('mail.mailers.smtp.host');
        $this->check('Mail is not the dev catcher', ! in_array($mailHost, ['mailpit', 'localhost', '127.0.0.1'], true) || filled(Settings::get('email.smtp_host')), $mailHost, fix: 'set the SMTP server in .env or Settings > Email', required: $production);

        $this->section('Database');
        try {
            DB::connection()->getPdo();
            $this->check('Database connection', true, DB::connection()->getDatabaseName());
            $this->check('Migrations have run', Schema::hasTable('settings') && Schema::hasTable('static_builds'), fix: 'run: php artisan migrate --force');
        } catch (Throwable $e) {
            $this->check('Database connection', false, $e->getMessage());
        }

        $this->section('Files and folders');
        foreach (['storage/app', 'storage/framework', 'storage/logs', 'bootstrap/cache'] as $dir) {
            $this->check("Writable: {$dir}", is_writable(base_path($dir)));
        }
        $static = $publisher->root();
        $this->check('Writable: public/static', is_dir($static) ? is_writable($static) : is_writable(dirname($static)));
        $this->check('Storage link (public/storage)', is_link(public_path('storage')) || is_dir(public_path('storage')), fix: 'run: php artisan storage:link');
        $this->check('Built assets (public/build)', is_file(public_path('build/manifest.json')), fix: 'run npm run build locally and upload public/build');
        $this->check('No Vite dev server file (public/hot)', ! is_file(public_path('hot')), fix: 'delete public/hot on the server');
        $this->check('.htaccess with static cache rules', str_contains((string) @file_get_contents(public_path('.htaccess')), 'static/'));

        $this->section('Running site');
        $lastRun = Cache::get('scheduler.last_run');
        $minutes = $lastRun ? (int) now()->diffInMinutes($lastRun, true) : null;
        $this->check('Cron runs every minute', $minutes !== null && $minutes <= 5, $lastRun ? "last run {$minutes} min ago" : 'never', fix: 'add the cron job (see docs/deploy-cpanel.md)', required: $production);
        $this->check('Static cache', ! $publisher->enabled() || $publisher->fileCount() > 0, $publisher->enabled() ? $publisher->fileCount().' pages built' : 'switched off', required: false);
        try {
            $this->check('No failed queue jobs', DB::table('failed_jobs')->count() === 0, fix: 'see: php artisan queue:failed', required: false);
        } catch (Throwable) {
        }

        $this->newLine();
        if ($this->failed) {
            $this->error("{$this->failed} required ".str('check')->plural($this->failed).' failed, '.$this->warnings.' '.str('warning')->plural($this->warnings).'.');

            return self::FAILURE;
        }

        $this->info('Ready.'.($this->warnings ? " {$this->warnings} ".str('warning')->plural($this->warnings).' to review.' : ''));

        return self::SUCCESS;
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->line("<options=bold>{$title}</>");
    }

    /**
     * @param  string  $detail  shown in both cases (current value)
     * @param  string  $fix  shown only when the check fails
     */
    private function check(string $label, bool $ok, string $detail = '', string $fix = '', bool $required = true): void
    {
        if ($ok) {
            $this->line("  <fg=green>✓</> {$label}".($detail !== '' ? " <fg=gray>({$detail})</>" : ''));

            return;
        }

        $required ? $this->failed++ : $this->warnings++;
        $mark = $required ? '<fg=red>✗</>' : '<fg=yellow>!</>';
        $info = implode(' – ', array_filter([$detail, $fix]));
        $this->line("  {$mark} {$label}".($info !== '' ? " <fg=gray>– {$info}</>" : ''));
    }
}
