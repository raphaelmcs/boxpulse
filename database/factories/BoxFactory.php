<?php

namespace Database\Factories;

use App\Models\Box;
use App\Models\TrainingModality;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Box> */
class BoxFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (Box $box): void {
            $box->modalities()->sync(TrainingModality::pluck('id')->all());
        });
    }

    public function definition(): array
    {
        return ['name' => fake()->company(), 'head_coach_name' => fake()->name(), 'timezone' => 'America/Sao_Paulo', 'primary_color' => '#C8FA3C', 'secondary_color' => '#38BDF8'];
    }
}
