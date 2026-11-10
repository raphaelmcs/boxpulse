<?php

namespace Tests\Concerns;

use App\Actions\SaveWorkout;
use App\Models\TrainingModality;
use App\Models\User;
use App\Models\Workout;
use App\Tenancy\CurrentBox;

trait InteractsWithWorkouts
{
    private function workoutInput(string $modality = 'crossfit', string $structure = 'main', string $status = 'published'): array
    {
        $blocks = [];
        if ($structure === 'full') {
            $blocks[] = ['type' => 'warmup', 'title' => 'Move well', 'description' => "2 rounds\n10 air squats", 'timer_type' => 'none'];
            $blocks[] = ['type' => 'skill', 'title' => 'Snatch livre', 'description' => 'Prática com barra vazia', 'timer_type' => 'countdown', 'duration_seconds' => 300];
        }
        $blocks[] = ['type' => 'workout', 'title' => 'Engine day', 'description' => "5 rounds\n200 m corrida\n10 burpees", 'timer_type' => $modality === 'functional' ? 'amrap' : 'for_time', 'duration_seconds' => 900, 'timer_settings' => ['preparation_seconds' => 10]];

        return ['training_modality_id' => TrainingModality::where('slug', $modality)->value('id'), 'structure' => $structure, 'title' => 'Aula '.uniqid(), 'scheduled_for' => '2026-11-10', 'status' => $status, 'blocks' => $blocks];
    }

    private function createWorkoutFor(User $user, ?array $input = null): Workout
    {
        $context = app(CurrentBox::class);
        $context->clear();
        $context->set($user->box_id);
        try {
            return app(SaveWorkout::class)->handle($user, $input ?? $this->workoutInput());
        } finally {
            $context->clear();
        }
    }
}
