<?php

namespace Database\Factories;

use App\Models\WorkoutBlock;
use App\Models\WorkoutStation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkoutStation> */
class WorkoutStationFactory extends Factory
{
    public function definition(): array
    {
        return ['workout_block_id' => WorkoutBlock::factory(), 'position' => 0, 'name' => 'SkiErg', 'distance' => 1000, 'description' => 'Ritmo consistente'];
    }
}
