<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;

#[WithoutTimestamps]
#[Fillable(['path', 'status', 'error', 'built_at'])]
class StaticBuild extends Model
{
    protected function casts(): array
    {
        return [
            'built_at' => 'datetime',
        ];
    }
}
