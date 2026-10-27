<?php

namespace App\Support;

/**
 * Spam checks for the public forms, which have no CSRF token (pages are static HTML).
 *
 * - A signed timestamp: forms rendered (or, with JS, focused) less than
 *   MIN_SECONDS before submitting are bots.
 * - A honeypot field ("website") people never see.
 * Rate limiting and the same-origin check live in the routes / middleware.
 */
class FormGuard
{
    public const MIN_SECONDS = 3;

    public const FIELD = '_started';

    public const HONEYPOT = 'website';

    /** "1761580000.<hmac>" */
    public static function token(?int $time = null): string
    {
        $time ??= time();

        return $time.'.'.static::sign($time);
    }

    /**
     * Why a submission looks automated, or null when it looks human.
     * Silent drops (honeypot, too fast) are treated as success so bots learn nothing.
     *
     * @return 'invalid'|'honeypot'|'too_fast'|null
     */
    public static function check(array $input): ?string
    {
        if (filled($input[static::HONEYPOT] ?? null)) {
            return 'honeypot';
        }

        $parts = explode('.', (string) ($input[static::FIELD] ?? ''), 2);

        if (count($parts) !== 2 || ! ctype_digit($parts[0]) || ! hash_equals(static::sign((int) $parts[0]), $parts[1])) {
            return 'invalid';
        }

        return time() - (int) $parts[0] < static::MIN_SECONDS ? 'too_fast' : null;
    }

    private static function sign(int $time): string
    {
        return hash_hmac('sha256', 'form-start:'.$time, (string) config('app.key'));
    }
}
