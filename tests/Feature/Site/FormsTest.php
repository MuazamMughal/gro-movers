<?php

namespace Tests\Feature\Site;

use App\Enums\LeadStatus;
use App\Http\Requests\Forms\QuoteRequest;
use App\Mail\ContactReceived;
use App\Mail\LeadAutoReply;
use App\Mail\LeadReceived;
use App\Models\ContactMessage;
use App\Models\Lead;
use App\Models\MoveType;
use App\Support\FormGuard;
use App\Support\Settings;
use Database\Seeders\MenuSeeder;
use Database\Seeders\MoveTypeSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SystemPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class FormsTest extends TestCase
{
    use RefreshDatabase;

    private MoveType $moveType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([SettingsSeeder::class, SystemPagesSeeder::class, MoveTypeSeeder::class, MenuSeeder::class]);
        $this->moveType = MoveType::query()->active()->ordered()->firstOrFail();
        Mail::fake();
        RateLimiter::clear('form-quote|127.0.0.1');
    }

    private function quote(array $overrides = []): array
    {
        return [
            'full_name' => 'Jessica Miller',
            'email' => 'Jessica@Example.com',
            'phone' => '(214) 555-0199',
            // Picked from a Photon suggestion: every part is filled.
            'from_address' => '1200 Main Street, West End Historic District, Dallas, TX 75202',
            'from_house_number' => '1200', 'from_street' => 'Main Street', 'from_neighborhood' => 'West End Historic District',
            'from_city' => 'Dallas', 'from_county' => 'Dallas', 'from_state' => 'tx', 'from_state_name' => 'Texas', 'from_zip' => '75202',
            'from_country' => 'United States', 'from_country_code' => 'us', 'from_lat' => '32.7797528', 'from_lng' => '-96.8015566', 'from_osm_id' => 'N12021744966',
            // Typed by hand: only the city/state/ZIP the old fields had.
            'to_address' => '5000 Legacy Dr, Plano, TX 75024',
            'to_city' => 'Plano', 'to_state' => 'TX', 'to_zip' => '75024', 'to_lat' => '', 'to_lng' => '',
            'move_date' => now()->addWeeks(3)->toDateString(),
            'move_type_id' => $this->moveType->id,
            'website' => '',
            '_started' => FormGuard::token(time() - 30),
            'source_url' => 'http://localhost/services/local-moving',
            'referrer' => 'https://www.google.com/',
            'utm' => json_encode(['utm_source' => 'google', 'utm_medium' => 'cpc', 'evil' => 'x']),
            ...$overrides,
        ];
    }

    private function fromSite(): static
    {
        return $this->withHeaders(['Origin' => config('app.url')]);
    }

    public function test_a_quote_request_is_saved_and_both_emails_are_queued()
    {
        $this->fromSite()->postJson('/forms/quote', $this->quote())
            ->assertOk()
            ->assertExactJson(['ok' => true, 'redirect' => '/thank-you']);

        $lead = Lead::sole();
        $this->assertSame('jessica@example.com', $lead->email);
        $this->assertSame('+12145550199', $lead->phone);
        $this->assertSame('1200 Main Street, West End Historic District, Dallas, TX 75202', $lead->from_address);
        $this->assertSame(
            ['1200', 'Main Street', 'West End Historic District', 'Dallas', 'Dallas', 'TX', 'Texas', '75202', 'United States', 'US', '32.7797528', '-96.8015566', 'N12021744966'],
            array_values($lead->only(array_map(fn ($part) => "from_{$part}", QuoteRequest::ADDRESS_PARTS))),
        );
        $this->assertNull($lead->to_street);
        $this->assertNull($lead->to_country);
        $this->assertSame($this->moveType->name, $lead->move_type_name);
        $this->assertSame(LeadStatus::New, $lead->status);
        $this->assertEquals(['utm_source' => 'google', 'utm_medium' => 'cpc'], $lead->utm);
        $this->assertSame('https://www.google.com/', $lead->referrer);
        $this->assertNull($lead->to_lat);

        Mail::assertQueued(LeadReceived::class, fn (LeadReceived $m) => $m->hasTo('admin@example.com') && $m->lead->is($lead));
        Mail::assertQueued(LeadAutoReply::class, fn (LeadAutoReply $m) => $m->hasTo('jessica@example.com'));
    }

    public function test_without_js_the_form_redirects_to_the_thank_you_page()
    {
        $this->fromSite()->post('/forms/quote', $this->quote())->assertRedirect('/thank-you?sent=quote');

        $this->assertSame(1, Lead::count());
    }

    public function test_the_auto_reply_can_be_switched_off()
    {
        Settings::set('email.auto_reply_enabled', false);
        Settings::flush();

        $this->fromSite()->postJson('/forms/quote', $this->quote())->assertOk();

        Mail::assertQueued(LeadReceived::class);
        Mail::assertNotQueued(LeadAutoReply::class);
    }

    public function test_validation_errors_as_json_for_the_js_form()
    {
        MoveType::factory()->create(['is_active' => false, 'name' => 'Hidden']);

        $this->fromSite()->postJson('/forms/quote', $this->quote([
            'full_name' => 'J',
            'email' => 'not-an-email',
            'phone' => '555-0100',
            'from_address' => 'TX',
            'move_date' => now()->subDay()->toDateString(),
            'move_type_id' => MoveType::where('name', 'Hidden')->value('id'),
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['full_name', 'email', 'phone', 'from_address', 'move_date', 'move_type_id'])
            ->assertJsonPath('errors.phone.0', 'Please enter a valid US phone number, e.g. (214) 555-0100.');

        // Hidden address parts that did not come from a US Photon result are refused.
        $this->fromSite()->postJson('/forms/quote', $this->quote(['from_country_code' => 'CA', 'from_osm_id' => 'X1', 'from_zip' => 'M5V 2T6']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['from_country_code', 'from_osm_id', 'from_zip']);

        $this->fromSite()->postJson('/forms/quote', $this->quote(['move_date' => now()->addMonths(19)->toDateString()]))
            ->assertJsonValidationErrors(['move_date' => 'within the next 18 months']);

        $this->assertSame(0, Lead::count());
        Mail::assertNothingQueued();
    }

    public function test_without_js_errors_come_back_on_a_page_with_the_form_filled_in()
    {
        $this->fromSite()->post('/forms/quote', $this->quote(['email' => 'nope']))
            ->assertStatus(422)
            ->assertSee('Please check your details')
            ->assertSee('Please enter a valid email address.')
            ->assertSee('value="Jessica Miller"', false)
            ->assertSee('<meta name="robots" content="noindex,follow">', false);
    }

    public function test_posts_from_other_sites_are_rejected()
    {
        $this->withHeaders(['Origin' => 'https://evil.example'])->postJson('/forms/quote', $this->quote())->assertForbidden();
        $this->flushHeaders()->postJson('/forms/quote', $this->quote())->assertForbidden();
        $this->withHeaders(['Referer' => 'https://evil.example/get-a-quote'])->postJson('/forms/quote', $this->quote())->assertForbidden();
        $this->flushHeaders()->withHeaders(['Referer' => config('app.url').'/get-a-quote'])->postJson('/forms/quote', $this->quote())->assertOk();
        $this->assertSame(1, Lead::count());
    }

    public function test_bots_get_a_success_response_but_nothing_is_saved()
    {
        $this->fromSite()->postJson('/forms/quote', $this->quote(['website' => 'http://spam.example']))->assertOk()->assertJson(['ok' => true]);
        $this->fromSite()->postJson('/forms/quote', $this->quote(['_started' => FormGuard::token(time())]))->assertOk();

        $this->assertSame(0, Lead::count());
        Mail::assertNothingQueued();
    }

    public function test_a_forged_or_missing_start_time_asks_to_reload()
    {
        $this->fromSite()->postJson('/forms/quote', $this->quote(['_started' => (time() - 60).'.forged']))
            ->assertStatus(422)
            ->assertJsonPath('errors.form.0', 'Your form expired. Please reload the page and send it again.');

        $this->fromSite()->post('/forms/quote', $this->quote(['_started' => '']))->assertStatus(422)->assertSee('Your form expired');

        $this->assertSame(0, Lead::count());
    }

    public function test_quote_submissions_are_rate_limited_to_five_per_ten_minutes()
    {
        foreach (range(1, 5) as $i) {
            $this->fromSite()->postJson('/forms/quote', $this->quote(['email' => "a{$i}@example.com"]))->assertOk();
        }

        $this->fromSite()->postJson('/forms/quote', $this->quote())->assertStatus(429);
        $this->assertSame(5, Lead::count());
    }

    public function test_the_contact_form_saves_the_message_and_emails_it()
    {
        $this->fromSite()->postJson('/forms/contact', [
            'name' => 'Sam Lee', 'email' => 'sam@example.com', 'phone' => '', 'message' => 'Do you move pianos?',
            'website' => '', '_started' => FormGuard::token(time() - 10), 'source_url' => 'http://localhost/contact-us',
        ])->assertOk();

        $message = ContactMessage::sole();
        $this->assertNull($message->phone);
        $this->assertSame('http://localhost/contact-us', $message->source_url);
        Mail::assertQueued(ContactReceived::class, fn ($m) => $m->hasTo('admin@example.com'));

        $this->fromSite()->postJson('/forms/contact', ['name' => 'Sam', 'email' => 'sam@example.com', 'phone' => '12', 'message' => 'Hi there', '_started' => FormGuard::token(time() - 10)])
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_token_endpoint_returns_a_fresh_signed_start_time()
    {
        $token = $this->getJson('/forms/token')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json('token');

        $this->assertSame('too_fast', FormGuard::check(['_started' => $token]));
    }

    public function test_forms_render_hidden_guard_and_tracking_fields()
    {
        $html = $this->get('/get-a-quote')->assertOk()->getContent();

        $this->assertStringContainsString('name="_started" value="', $html);
        $this->assertStringContainsString('name="website"', $html);
        $this->assertStringContainsString('name="from_zip"', $html);
        $this->assertStringContainsString('name="to_county"', $html);
        $this->assertStringContainsString('name="to_osm_id"', $html);
        $this->assertStringContainsString('data-address-api="https://photon.komoot.io"', $html);
        $this->assertStringNotContainsString('maps.googleapis.com', $html);
        $this->assertStringContainsString('name="utm"', $html);
        $this->assertStringContainsString('data-island="quote-form"', $html);
        $this->assertStringContainsString('>Submit Request<', str_replace(["\n", '  '], '', $html));
        // Exactly the agreed visible fields.
        preg_match_all('/<(input|select|textarea)(?![^>]*type="hidden")[^>]*name="([a-z_]+)"/', $html, $m);
        $this->assertSame(['full_name', 'email', 'phone', 'from_address', 'to_address', 'move_date', 'move_type_id', 'website'], array_values(array_unique($m[2])));
    }

    public function test_thank_you_page_confirms_and_is_noindex()
    {
        $this->get('/thank-you?sent=quote')
            ->assertOk()
            ->assertSee('Thank you!')
            ->assertSee('What happens next')
            ->assertSee('noindex', false);
    }

    public function test_emails_render_the_lead_and_the_auto_reply_tokens()
    {
        $this->fromSite()->postJson('/forms/quote', $this->quote())->assertOk();
        $lead = Lead::sole();

        $admin = (new LeadReceived($lead))->render();
        $this->assertStringContainsString('Jessica Miller', $admin);
        $this->assertStringContainsString('(214) 555-0199', $admin);
        $this->assertStringContainsString(route('admin.leads.show', $lead), $admin);
        $this->assertStringContainsString('google.com/maps/search', $admin);

        $reply = new LeadAutoReply($lead);
        $this->assertSame('We received your moving quote request', $reply->envelope()->subject);
        $this->assertStringContainsString('Hi Jessica Miller,', $reply->render());
        $this->assertStringContainsString($lead->move_date->format('l, F j, Y'), $reply->render());
    }
}
