<?php

namespace App\Models;

use App\Support\Settings;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['group', 'key', 'value'])]
class Setting extends Model
{
    protected static function booted(): void
    {
        static::saved(fn () => Settings::flush());
        static::deleted(fn () => Settings::flush());
    }

    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }
}
