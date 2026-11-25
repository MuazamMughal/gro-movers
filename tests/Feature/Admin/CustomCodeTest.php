<?php

namespace Tests\Feature\Admin;

use App\Models\Service;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SystemPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomCodeTest extends TestCase
{
    use RefreshDatabase;

    private const HEAD = '<script>window.headTag = 1;</script>';

    private const BODY = '<script src="https://widget.example.com/w.js" defer></script>';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([SettingsSeeder::class, SystemPagesSeeder::class]);
    }

    private function update(Service $service, array $seo): void
    {
        $this->put(route('admin.services.update', $service), [
            'title' => $service->title,
            'slug' => $service->slug,
            'short_description' => 'Fast, careful local movers.',
            'is_active' => true,
            'show_in_menu' => true,
            'show_on_home' => true,
            'faq_ids' => [],
            'seo' => ['robots_index' => true, 'robots_follow' => true, ...$seo],
            'sections' => [],
        ])->assertSessionHasNoErrors();
    }

    public function test_page_code_is_saved_and_printed_on_that_page_only()
    {
        $service = Service::factory()->create(['title' => 'Local Moving', 'slug' => 'local-moving']);
        $other = Service::factory()->create(['slug' => 'packing']);

        $this->actingAs(User::factory()->withPermissions(['services', 'custom_code'])->create());
        $this->update($service, ['head_code' => self::HEAD, 'body_code' => self::BODY]);

        $html = $this->get('/services/local-moving')->assertOk()->getContent();
        $this->assertLessThan(strpos($html, '</head>'), strpos($html, self::HEAD));
        $this->assertGreaterThan(strpos($html, '<footer'), strpos($html, self::BODY));
        $this->assertLessThan(strpos($html, '</body>'), strpos($html, self::BODY));

        $this->assertStringNotContainsString(self::HEAD, $this->get($other->publicPath())->getContent());
    }

    public function test_without_the_permission_the_code_is_kept_as_it_was()
    {
        $service = Service::factory()->create(['slug' => 'local-moving']);
        $service->seo()->create(['head_code' => self::HEAD]);

        $this->actingAs(User::factory()->withPermissions(['services'])->create());
        $this->update($service, ['meta_title' => 'Local Movers', 'head_code' => '<script>evil()</script>', 'body_code' => self::BODY]);

        $seo = $service->fresh()->seo;
        $this->assertSame('Local Movers', $seo->meta_title);
        $this->assertSame(self::HEAD, $seo->head_code);
        $this->assertNull($seo->body_code);
    }
}
