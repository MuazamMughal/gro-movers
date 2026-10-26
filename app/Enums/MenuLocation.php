<?php

namespace App\Enums;

enum MenuLocation: string
{
    case Header = 'header';
    case Footer1 = 'footer_1';
    case Footer2 = 'footer_2';
    case Footer3 = 'footer_3';
    case Mobile = 'mobile';

    public function label(): string
    {
        return match ($this) {
            self::Header => 'Header',
            self::Footer1 => 'Footer column 1',
            self::Footer2 => 'Footer column 2',
            self::Footer3 => 'Footer column 3',
            self::Mobile => 'Mobile menu',
        };
    }
}
