<?php

namespace App\Support;

/**
 * US phone numbers: "(214) 555-0100", "214.555.0100", "+1 214 555 0100" → "+12145550100".
 */
class Phone
{
    /** E.164 for a valid US number (NANP: area code and exchange start with 2–9), else null. */
    public static function e164(?string $input): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $input);

        if (strlen($digits) === 11 && $digits[0] === '1') {
            $digits = substr($digits, 1);
        }

        return preg_match('/^[2-9]\d{2}[2-9]\d{6}$/', $digits) ? '+1'.$digits : null;
    }

    /** "+12145550100" → "(214) 555-0100" (anything else is returned as is). */
    public static function display(?string $e164): string
    {
        return preg_match('/^\+1(\d{3})(\d{3})(\d{4})$/', (string) $e164, $m) ? "({$m[1]}) {$m[2]}-{$m[3]}" : (string) $e164;
    }
}
