<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\AreaServicePage;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AreaServicePage>
 */
class AreaServicePageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'area_id' => Area::factory(),
            'service_id' => Service::factory(),
            'is_active' => true,
            'use_template' => true,
            'title_override' => null,
        ];
    }
}
