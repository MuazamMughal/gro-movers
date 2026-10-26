<?php

namespace Database\Seeders;

use App\Models\MoveType;
use Illuminate\Database\Seeder;

class MoveTypeSeeder extends Seeder
{
    public const DEFAULTS = [
        'Local Move',
        'Long-Distance Move',
        'Residential Move',
        'Commercial Move',
        'Apartment Move',
        'Office Move',
    ];

    public function run(): void
    {
        if (MoveType::query()->exists()) {
            return;
        }

        foreach (self::DEFAULTS as $sort => $name) {
            MoveType::query()->create(['name' => $name, 'sort' => $sort, 'is_active' => true]);
        }
    }
}
