<?php

namespace App\Support;

use Illuminate\Mail\MailManager;

/**
 * Applies the admin Email settings (SMTP + from address) on top of the .env mail config.
 */
class MailSettings
{
    public static function apply(): void
    {
        $email = Settings::group('email');

        if (filled($email['from_address'] ?? null)) {
            config([
                'mail.from.address' => $email['from_address'],
                'mail.from.name' => $email['from_name'] ?? config('mail.from.name'),
            ]);
        }

        if (blank($email['smtp_host'] ?? null)) {
            return;
        }

        $encryption = $email['smtp_encryption'] ?? 'tls';

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $email['smtp_host'],
            'mail.mailers.smtp.port' => (int) ($email['smtp_port'] ?: ($encryption === 'ssl' ? 465 : 587)),
            'mail.mailers.smtp.username' => $email['smtp_username'] ?? null,
            'mail.mailers.smtp.password' => Settings::secret('email.smtp_password'),
            'mail.mailers.smtp.scheme' => $encryption === 'ssl' ? 'smtps' : 'smtp',
        ]);
    }

    /** Re-apply and drop the cached transport, so the next message uses fresh settings. */
    public static function refresh(): void
    {
        static::apply();

        app(MailManager::class)->purge('smtp');
    }
}
