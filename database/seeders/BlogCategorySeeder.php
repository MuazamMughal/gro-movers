<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * The three blog categories the client asked for. Idempotent: existing
 * categories (matched by URL) are left as edited in admin.
 */
class BlogCategorySeeder extends Seeder
{
    public const CATEGORIES = [
        'moving-tips' => ['Moving Tips', 'Practical advice from our crews to make moving day faster, safer and less stressful.'],
        'packing-guides' => ['Packing Guides', 'Step-by-step guides to packing every room, from fragile dishes to furniture and electronics.'],
        'moving-cost-guides' => ['Moving Cost Guides', 'What moving really costs in Texas and across the US, and how to plan your moving budget.'],
    ];

    public function run(): void
    {
        $sort = 0;

        foreach (static::CATEGORIES as $slug => [$name, $description]) {
            Category::withTrashed()->firstOrCreate(['slug' => $slug], [
                'name' => $name,
                'description' => $description,
                'sort' => $sort++,
            ]);
        }
    }
}
