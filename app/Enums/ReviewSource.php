<?php

namespace App\Enums;

enum ReviewSource: string
{
    case Google = 'google';
    case Yelp = 'yelp';
    case Facebook = 'facebook';
    case Website = 'website';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google',
            self::Yelp => 'Yelp',
            self::Facebook => 'Facebook',
            self::Website => 'Our website',
            self::Other => 'Other',
        };
    }

    /** @return array<string, string> value => label, for selects */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
