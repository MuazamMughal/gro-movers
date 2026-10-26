<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_auth_pages_live_under_the_admin_prefix()
    {
        $this->get('/admin/login')->assertOk();
        $this->get('/admin/forgot-password')->assertOk();
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_public_registration_is_disabled()
    {
        $this->get('/admin/register')->assertNotFound();
        $this->get('/register')->assertNotFound();
    }

    public function test_legacy_starter_kit_paths_are_gone()
    {
        $this->get('/login')->assertNotFound();
        $this->get('/dashboard')->assertNotFound();
    }

    public function test_settings_index_redirects_to_the_admin_profile_page()
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/settings')->assertRedirect('/admin/settings/profile');
    }

    public function test_public_home_page_renders()
    {
        $this->get('/')->assertOk()->assertSee(config('app.name'));
    }
}
