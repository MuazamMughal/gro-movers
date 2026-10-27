<?php

namespace App\Support;

/**
 * Company contact details from Settings, formatted for links.
 */
class Contact
{
    public static function phone(): string
    {
        return (string) Settings::get('general.phone', '');
    }

    /** "tel:+12145550100" for a US number in any common format. */
    public static function telHref(?string $phone = null): string
    {
        $digits = preg_replace('/\D+/', '', $phone ?? static::phone());

        if (strlen($digits) === 10) {
            $digits = '1'.$digits;
        }

        return 'tel:+'.$digits;
    }

    public static function email(): string
    {
        return (string) Settings::get('general.email', '');
    }

    /** One-line postal address. */
    public static function address(): string
    {
        $g = Settings::group('general');

        return trim(sprintf(
            '%s, %s, %s %s',
            $g['address_street'] ?? '',
            $g['address_city'] ?? '',
            $g['address_state'] ?? '',
            $g['address_zip'] ?? '',
        ), ' ,');
    }

    /**
     * @return list<array{days: string, hours: string}>
     */
    public static function hours(): array
    {
        return (array) Settings::get('general.business_hours', []);
    }
}
