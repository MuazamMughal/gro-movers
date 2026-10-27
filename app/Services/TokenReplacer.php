<?php

namespace App\Services;

use App\Models\Area;
use App\Models\Service;
use App\Support\Settings;

/**
 * Replaces content tokens such as {city} or {company} in admin-written text.
 * Unknown tokens are left untouched so typos stay visible to the admin.
 */
class TokenReplacer
{
    /** @var array<string, string> */
    private array $tokens;

    public function __construct(?Area $area = null, ?Service $service = null, array $extra = [])
    {
        $this->tokens = array_filter([
            'company' => (string) Settings::get('general.company_name', config('app.name')),
            'phone' => (string) Settings::get('general.phone', ''),
            'city' => $area?->city ?? Settings::get('general.address_city'),
            'state' => $area?->state_name,
            'state_code' => $area?->state_code ?? Settings::get('general.address_state'),
            'service' => $service?->title,
            ...$extra,
        ], fn ($v) => $v !== null && $v !== '');
    }

    public function replace(?string $text): ?string
    {
        if ($text === null || ! str_contains($text, '{')) {
            return $text;
        }

        return strtr($text, $this->map(fn (string $v) => $v));
    }

    /** For HTML content: token values are escaped before insertion. */
    public function replaceHtml(?string $html): ?string
    {
        if ($html === null || ! str_contains($html, '{')) {
            return $html;
        }

        return strtr($html, $this->map(fn (string $v) => e($v)));
    }

    /**
     * @return array<string, string>
     */
    public function tokens(): array
    {
        return $this->tokens;
    }

    /**
     * @param  callable(string): string  $format
     * @return array<string, string>
     */
    private function map(callable $format): array
    {
        $map = [];

        foreach ($this->tokens as $key => $value) {
            $map['{'.$key.'}'] = $format($value);
        }

        return $map;
    }
}
