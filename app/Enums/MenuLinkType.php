<?php

namespace App\Enums;

enum MenuLinkType: string
{
    case Page = 'page';
    case Service = 'service';
    case Area = 'area';
    case Post = 'post';
    case Category = 'category';
    case Url = 'url';
}
