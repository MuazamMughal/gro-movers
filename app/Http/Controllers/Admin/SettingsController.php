<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Settings\SettingsTabs;
use App\Support\MailSettings;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SettingsController extends Controller
{
    public function edit(string $tab): Response
    {
        abort_unless(SettingsTabs::exists($tab), 404);

        $fields = SettingsTabs::fields($tab);
        $values = [];

        foreach ($fields as $field) {
            // Secrets are never sent to the browser; the form only learns whether one is set.
            $values[$field['key']] = $field['type'] === 'secret'
                ? null
                : Settings::get($field['key']);
        }

        return Inertia::render('settings/Edit', [
            'tab' => $tab,
            'tabs' => Arr::map(SettingsTabs::all(), fn (array $t) => $t['label']),
            'description' => SettingsTabs::all()[$tab]['description'],
            'fields' => Arr::map($fields, fn (array $f) => Arr::except($f, ['rules', 'item_rules']) + [
                'required' => in_array('required', $f['rules'], true),
                'is_set' => $f['type'] === 'secret' ? filled(Settings::get($f['key'])) : null,
            ]),
            'values' => $values,
        ]);
    }

    public function update(Request $request, string $tab): RedirectResponse
    {
        abort_unless(SettingsTabs::exists($tab), 404);

        $validated = $request->validate(SettingsTabs::rules($tab), [], SettingsTabs::attributes($tab));

        foreach (SettingsTabs::fields($tab) as $field) {
            $value = Arr::get($validated, $field['key']);

            match ($field['type']) {
                'secret' => filled($value) ? Settings::setSecret($field['key'], $value) : null, // empty = keep
                default => Settings::set($field['key'], $this->normalize($field, $value)),
            };
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => SettingsTabs::all()[$tab]['label'].' settings saved.']);

        return to_route('admin.settings.edit', $tab);
    }

    /** Remove the stored SMTP password. */
    public function clearSecret(Request $request): RedirectResponse
    {
        $key = $request->validate(['key' => ['required', 'in:email.smtp_password']])['key'];

        Settings::setSecret($key, null);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Saved password removed.']);

        return back();
    }

    /** Send a test message to the signed-in admin using the saved email settings. */
    public function testEmail(Request $request): RedirectResponse
    {
        MailSettings::refresh();

        try {
            Mail::raw(
                'This is a test email from your website admin. If you received it, email delivery works.',
                fn ($message) => $message->to($request->user()->email)->subject('Test email from '.Settings::get('general.company_name')),
            );
        } catch (Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Sending failed: '.$e->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Test email sent to {$request->user()->email}."]);

        return back();
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function normalize(array $field, mixed $value): mixed
    {
        return match ($field['type']) {
            'toggle' => (bool) $value,
            'number' => $value === null || $value === '' ? null : $value + 0,
            'color' => strtoupper((string) $value),
            'email_list' => array_values(array_unique(array_map('strtolower', array_filter($value ?? [])))),
            'hours' => array_values($value ?? []),
            'social' => array_map(fn ($url) => $url ?: null, $value ?? []),
            'text' => match ($field['key']) {
                'integrations.search_console_verification' => $this->searchConsoleToken($value),
                'general.address_state' => $value === null ? null : strtoupper($value),
                default => $value,
            },
            default => $value,
        };
    }

    /** Accept either the full <meta name="google-site-verification" content="…"> tag or the token. */
    private function searchConsoleToken(?string $value): ?string
    {
        if ($value !== null && preg_match('/content\s*=\s*["\']([^"\']+)["\']/i', $value, $m)) {
            return $m[1];
        }

        return $value;
    }
}
