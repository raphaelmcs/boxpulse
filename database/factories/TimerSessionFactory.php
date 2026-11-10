<?php

namespace Database\Factories;

use App\Models\TimerSession;
use App\Models\WorkoutSession;
use App\Timers\CompileTimer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TimerSession> */
class TimerSessionFactory extends Factory
{
    public function definition(): array
    {
        return ['workout_session_id' => WorkoutSession::factory(), 'position' => 0, 'block_type' => 'workout', 'title' => 'AMRAP', 'description' => '10 burpees', 'timer_type' => 'amrap', 'config' => (new CompileTimer)->compile(['timer_type' => 'amrap', 'duration_seconds' => 60]), 'stations_snapshot' => []];
    }
}
