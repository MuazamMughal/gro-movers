<?php

namespace App\Models;

use Database\Factories\RedirectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

#[Fillable(['from_path', 'to_path', 'status_code', 'hits', 'last_hit_at', 'is_active'])]
class Redirect extends Model
{
    /** @use HasFactory<RedirectFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'hits' => 'integer',
            'last_hit_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Permanently redirect $from to $to, keeping the table free of loops and chains:
     * a redirect away from $to is removed (the URL is live again) and redirects
     * that pointed at $from are re-pointed straight to $to.
     */
    public static function point(string $from, string $to, int $status = 301): self
    {
        return DB::transaction(function () use ($from, $to, $status) {
            static::query()->where('from_path', $to)->delete();
            static::query()->where('to_path', $from)->update(['to_path' => $to]);

            return static::query()->updateOrCreate(
                ['from_path' => $from],
                ['to_path' => $to, 'status_code' => $status, 'is_active' => true],
            );
        });
    }
}
