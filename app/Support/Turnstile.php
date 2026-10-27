<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cloudflare Turnstile for the public forms. Active only when both keys are
 * set in Settings > Integrations; otherwise the honeypot, signed start time
 * and rate limit protect the forms on their own.
 */
class Turnstile
{
    public const FIELD = 'cf-turnstile-response';

    public const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public const SCRIPT_URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

    public static function siteKey(): ?string
    {
        return static::enabled() ? (string) Settings::get('integrations.turnstile_site_key') : null;
    }

    public static function enabled(): bool
    {
        return filled(Settings::get('integrations.turnstile_site_key')) && filled(Settings::secret('integrations.turnstile_secret_key'));
    }

    /**
     * Whether the visitor passed the challenge. Cloudflare being unreachable
     * lets the submission through (logged): losing a real lead costs more than
     * one bot that also got past the other checks.
     */
    public static function passes(?string $token, string $action, ?string $ip): bool
    {
        if (! static::enabled()) {
            return true;
        }

        if (blank($token) || strlen($token) > 2048) {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(5)->post(static::VERIFY_URL, [
                'secret' => Settings::secret('integrations.turnstile_secret_key'),
                'response' => $token,
                'remoteip' => $ip,
            ]);
        } catch (Throwable $e) {
            Log::warning('Turnstile verification unreachable; submission allowed', ['error' => $e->getMessage()]);

            return true;
        }

        if ($response->serverError()) {
            Log::warning('Turnstile verification failed with HTTP '.$response->status().'; submission allowed');

            return true;
        }

        $data = $response->json() ?? [];

        if (! ($data['success'] ?? false)) {
            Log::info('Turnstile rejected a form submission', ['action' => $action, 'errors' => $data['error-codes'] ?? []]);

            return false;
        }

        // A token solved for the other form (or another site) is not valid here.
        return blank($data['action'] ?? null) || $data['action'] === $action;
    }
}
