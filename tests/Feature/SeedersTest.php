<?php

namespace Tests\Feature;

use App\Enums\MenuLocation;
use App\Enums\SystemPage;
use App\Models\Menu;
use App\Models\MoveType;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeedersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'settings.admin.email' => 'owner@example.com',
            'settings.admin.password' => 'secret-test-password',
        ]);
    }

    public function test_base_seed_creates_everything_the_site_needs()
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(count(config('settings.defaults')), Setting::count());
        $this->assertSame(count(SystemPage::cases()), Page::count());
        $this->assertSame(6, MoveType::count());
        $this->assertSame(count(MenuLocation::cases()), Menu::count());

        $admin = User::sole();
        $this->assertSame('owner@example.com', $admin->email);
        $this->assertTrue(Hash::check('secret-test-password', $admin->password));
    }

    public function test_seeding_twice_is_idempotent_and_keeps_admin_changes()
    {
        $this->seed(DatabaseSeeder::class);
        Settings::set('general.company_name', 'Changed In Admin');
        MoveType::first()->update(['name' => 'Renamed']);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(count(config('settings.defaults')), Setting::count());
        $this->assertSame(count(SystemPage::cases()), Page::count());
        $this->assertSame(6, MoveType::count());
        $this->assertSame(1, User::count());
        $this->assertSame('Changed In Admin', Settings::get('general.company_name'));
        $this->assertTrue(MoveType::where('name', 'Renamed')->exists());
    }

    public function test_admin_gets_a_generated_password_when_none_is_configured()
    {
        config(['settings.admin.password' => null]);

        $this->seed(DatabaseSeeder::class);

        $this->assertNotEmpty(User::sole()->password);
    }

    public function test_thank_you_page_is_seeded_as_noindex()
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertFalse(Page::system(SystemPage::ThankYou)->seoIndexable());
        $this->assertTrue(Page::system(SystemPage::Home)->seoIndexable());
    }
}
