<?php

namespace Database\Factories;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\MoveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => '+1214'.fake()->numerify('#######'),
            'from_address' => fake()->streetAddress().', Dallas, TX 75201',
            'from_city' => 'Dallas',
            'from_county' => 'Dallas',
            'from_state' => 'TX',
            'from_state_name' => 'Texas',
            'from_zip' => '75201',
            'from_country' => 'United States',
            'from_country_code' => 'US',
            'to_address' => fake()->streetAddress().', Plano, TX 75024',
            'to_city' => 'Plano',
            'to_county' => 'Collin',
            'to_state' => 'TX',
            'to_state_name' => 'Texas',
            'to_zip' => '75024',
            'to_country' => 'United States',
            'to_country_code' => 'US',
            'move_date' => now()->addDays(fake()->numberBetween(7, 90)),
            'move_type_id' => MoveType::factory(),
            'move_type_name' => fn (array $attributes) => MoveType::find($attributes['move_type_id'])?->name ?? 'Local Move',
            'status' => LeadStatus::New,
            'source_url' => '/get-a-quote',
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
