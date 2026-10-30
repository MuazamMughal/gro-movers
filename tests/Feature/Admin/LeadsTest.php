<?php

namespace Tests\Feature\Admin;

use App\Enums\LeadStatus;
use App\Models\ContactMessage;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LeadsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        $this->admin = User::factory()->create();
        $this->actingAs($this->admin);
    }

    public function test_index_filters_and_unread_counts()
    {
        Lead::factory()->create(['full_name' => 'Ana Ruiz', 'phone' => '+12145550111', 'status' => LeadStatus::New]);
        Lead::factory()->create(['full_name' => 'Ben Tate', 'status' => LeadStatus::Booked, 'read_at' => now()]);

        $this->get(route('admin.leads.index', ['q' => '555-0111']))->assertInertia(fn (Assert $page) => $page
            ->component('leads/Index')
            ->has('leads.data', 1)
            ->where('leads.data.0.full_name', 'Ana Ruiz')
            ->where('counts.unread', 1)
            ->where('counts.all', 2));

        $this->get(route('admin.leads.index', ['status' => 'booked']))->assertInertia(fn (Assert $page) => $page->where('leads.data.0.full_name', 'Ben Tate'));
        $this->get(route('admin.leads.index', ['unread' => '1']))->assertInertia(fn (Assert $page) => $page->has('leads.data', 1));
        $this->get(route('admin.leads.index', ['from' => now()->addDay()->toDateString()]))->assertInertia(fn (Assert $page) => $page->has('leads.data', 0));

        // Sidebar badge.
        $this->get(route('admin.leads.index'))->assertInertia(fn (Assert $page) => $page->where('inbox.leads', 1));
    }

    public function test_opening_a_lead_marks_it_read_and_status_and_notes_can_change()
    {
        $lead = Lead::factory()->create([
            'phone' => '+12145550111',
            'from_house_number' => '1200', 'from_street' => 'Main Street', 'from_lat' => '32.7797528', 'from_lng' => '-96.8015566', 'from_osm_id' => 'W432096036',
            'to_address' => 'Plano', 'to_city' => null, 'to_county' => null, 'to_state' => null, 'to_state_name' => null, 'to_zip' => null, 'to_country' => null, 'to_country_code' => null,
        ]);

        $this->get(route('admin.leads.show', $lead))->assertInertia(fn (Assert $page) => $page
            ->component('leads/Show')
            ->where('lead.phone', '(214) 555-0111')
            ->where('lead.phone_href', 'tel:+12145550111')
            ->where('lead.from.parts', [
                'Street' => '1200 Main Street', 'City' => 'Dallas', 'County' => 'Dallas', 'State' => 'Texas (TX)',
                'ZIP' => '75201', 'Country' => 'United States', 'Coordinates' => '32.7797528, -96.8015566',
            ])
            ->where('lead.from.osm', 'https://www.openstreetmap.org/way/432096036')
            ->where('lead.to.address', 'Plano')
            ->where('lead.to.parts', [])
            ->where('lead.to.osm', null));
        $this->assertNotNull($lead->fresh()->read_at);

        $this->patch(route('admin.leads.update', $lead), ['status' => 'quoted']);
        $this->assertSame(LeadStatus::Quoted, $lead->fresh()->status);
        $this->assertNotNull($lead->fresh()->read_at);

        $this->patch(route('admin.leads.update', $lead), ['read' => false]);
        $this->assertNull($lead->fresh()->read_at);
        $this->assertSame(LeadStatus::Quoted, $lead->fresh()->status);

        $this->post(route('admin.leads.notes.store', $lead), ['body' => 'Called, left voicemail.']);
        $note = $lead->notes()->sole();
        $this->assertSame($this->admin->id, $note->user_id);

        $this->delete(route('admin.leads.notes.destroy', [$lead, $note]));
        $this->assertSame(0, $lead->notes()->count());

        $this->delete(route('admin.leads.destroy', $lead));
        $this->assertSoftDeleted($lead);
    }

    public function test_csv_export_respects_filters_and_neutralises_formulas()
    {
        Lead::factory()->create(['full_name' => '=HYPERLINK("http://evil")', 'status' => LeadStatus::New]);
        Lead::factory()->create(['full_name' => 'Lost Lead', 'status' => LeadStatus::Lost]);

        $csv = $this->get(route('admin.leads.export', ['status' => 'new']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('"\'=HYPERLINK(""http://evil"")"', $csv);
        $this->assertStringContainsString('"From county","From state","From state name"', $csv);
        $this->assertStringContainsString(',Dallas,Dallas,TX,Texas,75201,"United States",US,', $csv);
        $this->assertStringNotContainsString('Lost Lead', $csv);
        $this->assertSame(2, substr_count(trim($csv), "\n") + 1);
    }

    public function test_contact_inbox_read_unread_and_delete()
    {
        $message = ContactMessage::factory()->create(['name' => 'Sam Lee', 'read_at' => null]);

        $this->get(route('admin.messages.index'))->assertInertia(fn (Assert $page) => $page
            ->component('messages/Index')
            ->where('unreadCount', 1)
            ->where('messages.data.0.name', 'Sam Lee'));

        $this->get(route('admin.messages.show', $message))->assertRedirect(route('admin.messages.index', ['open' => $message->id]));
        $this->assertNotNull($message->fresh()->read_at);

        $this->patch(route('admin.messages.update', $message), ['read' => false]);
        $this->assertNull($message->fresh()->read_at);

        $this->delete(route('admin.messages.destroy', $message));
        $this->assertSoftDeleted($message);
    }

    public function test_guests_cannot_see_leads()
    {
        auth()->logout();

        $this->get(route('admin.leads.index'))->assertRedirect();
        $this->get(route('admin.leads.export'))->assertRedirect();
    }
}
