<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Settings\SettingsTabs;
use App\Support\MailSettings;
use App\Support\Settings;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        $this->actingAs(User::factory()->create());
    }

    public static function tabs(): array
    {
        return Arr::mapWithKeys(array_keys(SettingsTabs::all()), fn ($tab) => [$tab => [$tab]]);
    }

    #[DataProvider('tabs')]
    public function test_every_tab_renders(string $tab)
    {
        $this->get(route('admin.settings.edit', $tab))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Edit')
                ->where('tab', $tab)
                ->has('fields', count(SettingsTabs::fields($tab)))
                ->has('values')
            );
    }

    #[DataProvider('tabs')]
    public function test_every_tab_saves_its_seeded_defaults(string $tab)
    {
        $payload = [];

        foreach (SettingsTabs::fields($tab) as $field) {
            Arr::set($payload, $field['key'], config('settings.defaults')[$field['key']]);
        }

        $this->put(route('admin.settings.update', $tab), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.settings.edit', $tab));
    }

    public function test_unknown_tab_is_404()
    {
        $this->get(route('admin.settings.edit', 'nope'))->assertNotFound();
    }

    public function test_guests_cannot_open_settings()
    {
        auth()->logout();

        $this->get(route('admin.settings.edit', 'general'))->assertRedirect(route('login'));
    }

    public function test_general_settings_are_validated_and_normalized()
    {
        $payload = $this->generalPayload(['phone' => 'not a phone', 'address_state' => 'Texas']);

        $this->put(route('admin.settings.update', 'general'), $payload)
            ->assertSessionHasErrors(['general.phone', 'general.address_state']);

        $payload = $this->generalPayload(['address_state' => 'tx', 'company_name' => 'Acme Movers']);

        $this->put(route('admin.settings.update', 'general'), $payload)->assertSessionHasNoErrors();

        $this->assertSame('TX', Settings::get('general.address_state'));
        $this->assertSame('Acme Movers', Settings::get('general.company_name'));
    }

    public function test_theme_colors_must_be_hex_and_are_uppercased()
    {
        $colors = ['primary' => '#ffffff', 'secondary' => '#112233', 'tertiary' => '#aa5500', 'muted' => '#f0f0f0', 'border' => '#dddddd'];

        $this->put(route('admin.settings.update', 'theme'), ['theme' => [...$colors, 'tertiary' => 'red;}body{']])
            ->assertSessionHasErrors('theme.tertiary');

        $this->put(route('admin.settings.update', 'theme'), ['theme' => $colors])->assertSessionHasNoErrors();

        $this->assertSame('#AA5500', Settings::get('theme.tertiary'));
    }

    public function test_theme_variables_are_printed_on_the_public_site()
    {
        Settings::set('theme.tertiary', '#123ABC');

        $this->get('/')->assertOk()->assertSee('--color-tertiary:#123ABC', false);
    }

    public function test_an_invalid_stored_color_falls_back_to_the_default()
    {
        Settings::set('theme.secondary', 'red;}body{display:none');

        $this->get('/')
            ->assertSee('--color-secondary:'.config('settings.defaults')['theme.secondary'], false)
            ->assertDontSee('display:none', false);
    }

    public function test_smtp_password_is_encrypted_never_sent_back_and_kept_when_empty()
    {
        $payload = $this->emailPayload(['smtp_host' => 'smtp.example.com', 'smtp_password' => 's3cret-pass']);
        $this->put(route('admin.settings.update', 'email'), $payload)->assertSessionHasNoErrors();

        $this->assertNotSame('s3cret-pass', Settings::get('email.smtp_password'));
        $this->assertSame('s3cret-pass', Settings::secret('email.smtp_password'));

        $this->get(route('admin.settings.edit', 'email'))
            ->assertDontSee('s3cret-pass')
            ->assertInertia(fn (Assert $page) => $page->where('values', fn ($values) => $values['email.smtp_password'] === null));

        // Saving again with an empty password keeps the stored one.
        $this->put(route('admin.settings.update', 'email'), $this->emailPayload(['smtp_password' => '']))->assertSessionHasNoErrors();
        $this->assertSame('s3cret-pass', Settings::secret('email.smtp_password'));

        $this->delete(route('admin.settings.clear-secret'), ['key' => 'email.smtp_password']);
        $this->assertNull(Settings::secret('email.smtp_password'));
    }

    public function test_lead_recipients_must_be_valid_emails_and_are_deduplicated()
    {
        $this->put(route('admin.settings.update', 'email'), $this->emailPayload(['lead_recipients' => ['bad']]))
            ->assertSessionHasErrors('email.lead_recipients.0');

        $this->put(route('admin.settings.update', 'email'), $this->emailPayload(['lead_recipients' => ['A@Example.com', 'a@example.com', 'b@example.com']]))
            ->assertSessionHasNoErrors();

        $this->assertSame(['a@example.com', 'b@example.com'], Settings::get('email.lead_recipients'));
    }

    public function test_search_console_meta_tag_is_reduced_to_its_token()
    {
        $this->put(route('admin.settings.update', 'integrations'), ['integrations' => [
            'search_console_verification' => '<meta name="google-site-verification" content="abc123XYZ" />',
            'ga4_id' => 'G-ABC1234',
        ]])->assertSessionHasNoErrors();

        $this->assertSame('abc123XYZ', Settings::get('integrations.search_console_verification'));
    }

    public function test_analytics_ids_are_format_checked()
    {
        $this->put(route('admin.settings.update', 'integrations'), ['integrations' => ['ga4_id' => 'UA-1234', 'gtm_id' => 'GTM1']])
            ->assertSessionHasErrors(['integrations.ga4_id', 'integrations.gtm_id']);
    }

    public function test_mail_settings_override_the_mail_config()
    {
        Settings::setMany([
            'email.smtp_host' => 'smtp.mailhost.test',
            'email.smtp_port' => 465,
            'email.smtp_encryption' => 'ssl',
            'email.from_address' => 'leads@acme.test',
        ]);
        Settings::setSecret('email.smtp_password', 'pw');

        MailSettings::apply();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.mailhost.test', config('mail.mailers.smtp.host'));
        $this->assertSame(465, config('mail.mailers.smtp.port'));
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame('pw', config('mail.mailers.smtp.password'));
        $this->assertSame('leads@acme.test', config('mail.from.address'));
    }

    public function test_test_email_is_sent_to_the_signed_in_admin()
    {
        $this->post(route('admin.settings.test-email'))->assertRedirect();

        $messages = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $this->assertSame(auth()->user()->email, $messages[0]->getEnvelope()->getRecipients()[0]->getAddress());
    }

    private function generalPayload(array $overrides = []): array
    {
        return ['general' => array_merge(Settings::group('general'), $overrides)];
    }

    private function emailPayload(array $overrides = []): array
    {
        $values = Arr::except(Settings::group('email'), ['smtp_password']);

        return ['email' => array_merge($values, $overrides)];
    }
}
