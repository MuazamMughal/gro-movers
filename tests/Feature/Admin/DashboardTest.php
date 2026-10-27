<?php

namespace Tests\Feature\Admin;

use App\Models\ContactMessage;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_dashboard_shows_lead_stats_and_latest_leads()
    {
        Lead::factory()->count(2)->create();
        Lead::factory()->create(['created_at' => now()->subDays(10)]);
        Lead::factory()->create(['created_at' => now()->subDays(40), 'read_at' => now()]);
        ContactMessage::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('stats.leadsToday', 2)
                ->where('stats.leads7', 2)
                ->where('stats.leads30', 3)
                ->where('stats.unreadLeads', 3)
                ->where('stats.unreadMessages', 1)
                ->has('chart', 30)
                ->has('latestLeads', 4)
                ->where('staticSite.running', null)
                ->where('staticSite.failures', [])
            );
    }
}
