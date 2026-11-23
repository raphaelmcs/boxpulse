<?php

namespace Database\Factories;

use App\Models\Box;
use App\Models\LoadPreset;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LoadPreset> */
class LoadPresetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'box_id' => Box::factory(),
            'equipment' => 'Dumbbell',
            'movement' => 'Dumbbell Snatch',
            'levels' => ['rx' => ['male' => 22.5, 'female' => 15]],
            'unit' => 'kg',
            'reference' => fake()->words(3, true),
            'active' => true,
        ];
    }
}
