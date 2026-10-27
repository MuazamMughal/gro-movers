<?php

namespace Tests\Feature\Site;

use App\Models\ContactMessage;
use App\Models\Lead;
use App\Models\MoveType;
use App\Models\User;
use App\Support\FormGuard;
use App\Support\Settings;
use App\Support\Turnstile;
use Database\Seeders\MenuSeeder;
use Database\Seeders\MoveTypeSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\SystemPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** Cloudflare Turnstile on the public forms (T18). */
class TurnstileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([SettingsSeeder::class, SystemPagesSeeder::class, MoveTypeSeeder::class, MenuSeeder::class]);
        Mail::fake();
        Http::preventStrayRequests();
        RateLimiter::clear('form-quote|127.0.0.1');
        RateLimiter::clear('form-contact|127.0.0.1');
    }

    private function enable(): void
    {
        Settings::set('integrations.turnstile_site_key', '0x4AAAAAAA-site');
        Settings::setSecret('integrations.turnstile_secret_key', '0x4AAAAAAA-secret');
    }

    private function quote(array $overrides = []): array
    {
        return [
            'full_name' => 'Jessica Miller',
            'email' => 'jessica@example.com',
            'phone' => '(214) 555-0199',
            'from_address' => '1200 Main St, Dallas, TX 75201',
            'to_address' => '5000 Legacy Dr, Plano, TX 75024',
            'move_date' => now()->addWeeks(3)->toDateString(),
            'move_type_id' => MoveType::query()->active()->value('id'),
            'website' => '',
            '_started' => FormGuard::token(time() - 30),
            ...$overrides,
        ];
    }

    private function send(string $form, array $data, bool $json = true)
    {
        $this->withHeaders(['Origin' => config('app.url')]);

        return $json ? $this->postJson("/forms/{$form}", $data) : $this->post("/forms/{$form}", $data);
    }

    private function cloudflare(array $body, int $status = 200): void
    {
        Http::fake([Turnstile::VERIFY_URL => Http::response($body, $status)]);
    }

    public function test_without_keys_the_forms_skip_the_check()
    {
        $this->send('quote', $this->quote())->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(1, Lead::count());
        Http::assertNothingSent();
        $this->get('/get-a-quote')->assertOk()->assertDontSee('data-turnstile', false);

        // One key alone is not enough.
        Settings::set('integrations.turnstile_site_key', '0x4AAAAAAA-site');
        $this->assertFalse(Turnstile::enabled());
    }

    public function test_a_bot_post_without_a_token_is_rejected_while_keys_are_set()
    {
        $this->enable();
        $this->cloudflare(['success' => true, 'action' => 'quote']);

        $this->send('quote', $this->quote())
            ->assertUnprocessable()
            ->assertJsonPath('errors.form.0', 'Please complete the security check and send the form again.');

        $this->assertSame(0, Lead::count());
        Http::assertNothingSent(); // no token: nothing to verify
        Mail::assertNothingQueued();
    }

    public function test_a_plain_post_without_a_token_explains_javascript_is_needed()
    {
        $this->enable();

        $this->send('quote', $this->quote(), json: false)
            ->assertUnprocessable()
            ->assertSee('Our spam check needs JavaScript')
            ->assertSee(Settings::get('general.phone'))
            ->assertSee('Jessica Miller'); // the form is filled in again

        $this->assertSame(0, Lead::count());
    }

    public function test_a_valid_token_is_verified_server_side_and_the_lead_is_saved()
    {
        $this->enable();
        $this->cloudflare(['success' => true, 'action' => 'quote', 'hostname' => 'localhost']);

        $this->send('quote', $this->quote([Turnstile::FIELD => 'token-from-widget']))->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(1, Lead::count());
        Http::assertSent(fn (Request $r) => $r->url() === Turnstile::VERIFY_URL
            && $r['secret'] === '0x4AAAAAAA-secret'
            && $r['response'] === 'token-from-widget'
            && $r['remoteip'] === '127.0.0.1');
    }

    public function test_rejected_tokens_and_tokens_for_the_other_form_fail()
    {
        $this->enable();

        $this->cloudflare(['success' => false, 'error-codes' => ['timeout-or-duplicate']]);
        $this->send('quote', $this->quote([Turnstile::FIELD => 'reused']))->assertUnprocessable();

        $this->cloudflare(['success' => true, 'action' => 'contact']);
        $this->send('quote', $this->quote([Turnstile::FIELD => 'from-contact-form']))->assertUnprocessable();

        $this->assertSame(0, Lead::count());
    }

    public function test_the_contact_form_is_protected_too()
    {
        $this->enable();
        $message = ['name' => 'Sam Lee', 'email' => 'sam@example.com', 'message' => 'Do you move pianos?', 'website' => '', '_started' => FormGuard::token(time() - 30)];

        $this->send('contact', $message)->assertUnprocessable();

        $this->cloudflare(['success' => true, 'action' => 'contact']);
        $this->send('contact', $message + [Turnstile::FIELD => 'ok'])->assertOk();

        $this->assertSame(1, ContactMessage::count());
    }

    public function test_cloudflare_being_down_does_not_lose_leads()
    {
        $this->enable();

        Http::fake([Turnstile::VERIFY_URL => fn () => throw new ConnectionException('timed out')]);
        $this->send('quote', $this->quote([Turnstile::FIELD => 'token']))->assertOk();

        $this->cloudflare([], 503);
        $this->send('quote', $this->quote([Turnstile::FIELD => 'token', 'email' => 'second@example.com']))->assertOk();

        $this->assertSame(2, Lead::count());
    }

    public function test_honeypot_and_timing_checks_still_run_first()
    {
        $this->enable();

        $this->send('quote', $this->quote(['website' => 'spam.example', Turnstile::FIELD => 'x']))->assertOk(); // silent drop
        $this->assertSame(0, Lead::count());
        Http::assertNothingSent();
    }

    public function test_forms_render_the_lazy_widget_slot_and_never_the_script()
    {
        $this->enable();

        foreach (['/get-a-quote' => 'quote', '/contact-us' => 'contact'] as $path => $action) {
            $this->get($path)
                ->assertOk()
                ->assertSee('data-sitekey="0x4AAAAAAA-site" data-action="'.$action.'"', false)
                ->assertSee('<noscript>', false)
                ->assertDontSee('challenges.cloudflare.com', false);
        }
    }

    public function test_keys_are_saved_in_settings_with_the_secret_encrypted()
    {
        $this->actingAs(User::factory()->create());

        $this->put(route('admin.settings.update', 'integrations'), ['integrations' => [
            'turnstile_site_key' => '0x4AAAAAAABBBBBB',
            'turnstile_secret_key' => '0x4AAAAAAA-top-secret',
        ]])->assertSessionHasNoErrors();

        $this->assertSame('0x4AAAAAAABBBBBB', Settings::get('integrations.turnstile_site_key'));
        $this->assertNotSame('0x4AAAAAAA-top-secret', Settings::get('integrations.turnstile_secret_key'));
        $this->assertSame('0x4AAAAAAA-top-secret', Settings::secret('integrations.turnstile_secret_key'));
        $this->assertTrue(Turnstile::enabled());

        $this->put(route('admin.settings.update', 'integrations'), ['integrations' => ['turnstile_site_key' => '<script>']])
            ->assertSessionHasErrors('integrations.turnstile_site_key');
    }
}
