<?php

namespace Tests\Feature\Console;

use App\Models\Service;
use App\Models\User;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** cPanel deployment tooling (docs/deploy-cpanel.md): cron schedule, backups, deploy:check. */
class DeployToolsTest extends TestCase
{
    use RefreshDatabase;

    private string $backups;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backups = storage_path('app/backups');
        File::moveDirectory($this->backups, $this->backups.'.keep');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->backups);
        File::moveDirectory($this->backups.'.keep', $this->backups);

        parent::tearDown();
    }

    public function test_one_cron_line_runs_everything_the_site_needs()
    {
        $events = collect(app(Schedule::class)->events());
        $find = fn (string $needle) => $events->first(fn (Event $e) => str_contains((string) $e->command.' '.$e->description, $needle));

        $this->assertSame('* * * * *', $find('blog:publish-scheduled')->expression);
        $this->assertSame('* * * * *', $find('scheduler:heartbeat')->expression);
        $this->assertSame('30 3 * * *', $find('backup:run')->expression);

        $worker = $find('queue:cron-worker');
        $this->assertStringContainsString('--stop-when-empty', $worker->command);
        $this->assertTrue($worker->withoutOverlapping);

        config(['queue.cron_worker' => false]); // Sail runs its own worker
        $this->assertFalse($worker->filtersPass($this->app));
        config(['queue.cron_worker' => true]);
        $this->assertTrue($worker->filtersPass($this->app));
    }

    public function test_heartbeat_feeds_the_dashboard_cron_warning()
    {
        $this->actingAs(User::factory()->create());
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('schedulerLastRun', null));

        $this->artisan('schedule:run')->assertSuccessful();

        $this->assertNotNull(Cache::get('scheduler.last_run'));
        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page->where('schedulerLastRun', Cache::get('scheduler.last_run')));
    }

    public function test_backup_writes_a_restorable_dump_and_keeps_the_newest()
    {
        Service::factory()->create(['title' => "Mover's \"Best\" Service"]);

        $this->artisan('backup:run', ['--no-media' => true])->expectsOutputToContain('Database: database-')->assertSuccessful();

        $this->assertFileExists($this->backups.'/.htaccess');
        $dumps = glob($this->backups.'/database-*.sql.gz');
        $this->assertCount(1, $dumps);

        $sql = gzdecode(file_get_contents($dumps[0]));
        $this->assertStringContainsString('CREATE TABLE `services`', $sql);
        $this->assertStringContainsString("Mover\\'s \\\"Best\\\" Service", $sql);
        $this->assertStringContainsString('CREATE TABLE `sessions`', $sql);
        $this->assertStringNotContainsString('INSERT INTO `sessions`', $sql);

        foreach (['2020-01-01_000000', '2020-01-02_000000'] as $stamp) {
            touch($this->backups."/database-{$stamp}.sql.gz");
        }
        $this->travel(1)->minute(); // a new timestamped name
        $this->artisan('backup:run', ['--no-media' => true, '--keep' => 2])->assertSuccessful();

        $left = array_map('basename', glob($this->backups.'/database-*.sql.gz'));
        $this->assertCount(2, $left);
        $this->assertNotContains('database-2020-01-01_000000.sql.gz', $left);
        $this->assertNotContains('database-2020-01-02_000000.sql.gz', $left);
    }

    public function test_deploy_check_reports_and_fails_on_required_checks()
    {
        // The test environment is not production, so only universal checks are required.
        $this->artisan('deploy:check')
            ->expectsOutputToContain('PHP 8.3 or newer')
            ->expectsOutputToContain('Database connection')
            ->expectsOutputToContain('Cron runs every minute');

        config(['app.key' => '']);
        $this->artisan('deploy:check')->expectsOutputToContain('run: php artisan key:generate')->assertFailed();
    }

    public function test_deploy_check_is_strict_in_production()
    {
        $this->app['env'] = 'production';
        config(['app.debug' => true, 'app.url' => 'http://example.com']);

        $this->artisan('deploy:check')
            ->expectsOutputToContain('APP_DEBUG=false')
            ->expectsOutputToContain('add the cron job')
            ->assertFailed();
    }
}
