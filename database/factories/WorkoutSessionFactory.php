<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSession;
use App\Tenancy\CurrentBox;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkoutSession> */
class WorkoutSessionFactory extends Factory
{
    public function definition(): array
    {
        return ['workout_id' => Workout::factory(), 'responsible_user_id' => User::factory()->state(['box_id' => app(CurrentBox::class)->requireId()]), 'title' => 'Aula demo', 'modality_name' => 'CrossFit', 'scheduled_for' => now()->toDateString()];
    }
}
