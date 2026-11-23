<?php

namespace Database\Factories;

use App\Models\Box;
use App\Models\LoadMovement;
use App\Workouts\LoadLibrary;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LoadMovement> */
class LoadMovementFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'box_id' => Box::factory(), 'name' => $name, 'normalized_name' => LoadLibrary::key($name),
            'equipment' => 'Barbell', 'modalities' => ['crossfit'], 'aliases' => [], 'active' => true,
        ];
    }
}
