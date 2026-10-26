<?php

namespace App\Enums;

enum ReviewSource: string
{
    case Google = 'google';
    case Yelp = 'yelp';
    case Facebook = 'facebook';
    case Website = 'website';
    case Other = 'other';
}
