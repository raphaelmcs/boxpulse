<?php

namespace Database\Factories;

use App\Models\Workout;
use App\Models\WorkoutBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkoutBlock> */
class WorkoutBlockFactory extends Factory
{
    public function definition(): array
    {
        return ['workout_id' => Workout::factory(), 'type' => 'workout', 'position' => 0, 'title' => 'Engine day', 'description' => "5 rounds\n10 burpees\n200 m run", 'timer_type' => 'for_time', 'duration_seconds' => 900, 'timer_settings' => ['version' => 1, 'preparation_seconds' => 10]];
    }
}
