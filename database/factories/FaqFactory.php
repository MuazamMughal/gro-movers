<?php

namespace Database\Factories;

use App\Models\Faq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faq>
 */
class FaqFactory extends Factory
{
    public function definition(): array
    {
        return [
            'question' => rtrim(fake()->unique()->sentence(8), '.').'?',
            'answer' => '<p>'.fake()->paragraph().'</p>',
            'sort' => 0,
            'is_active' => true,
        ];
    }
}
