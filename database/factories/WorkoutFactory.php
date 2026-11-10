<?php

namespace Database\Factories;

use App\Models\TrainingModality;
use App\Models\Workout;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Workout> */
class WorkoutFactory extends Factory
{
    public function definition(): array
    {
        return ['training_modality_id' => TrainingModality::where('slug', 'crossfit')->value('id'), 'title' => fake()->sentence(3), 'scheduled_for' => now('America/Sao_Paulo')->toDateString(), 'status' => 'draft'];
    }
}
