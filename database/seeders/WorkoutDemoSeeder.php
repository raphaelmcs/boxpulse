<?php

namespace Database\Seeders;

use App\Actions\SaveWorkout;
use App\Models\TrainingModality;
use App\Models\User;
use App\Models\Workout;
use App\Tenancy\CurrentBox;
use Illuminate\Database\Seeder;

class WorkoutDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }
        $user = User::where('username', 'boxpulse.demo')->first();
        if (! $user?->box_id) {
            return;
        }
        $context = app(CurrentBox::class);
        $context->set($user->box_id);
        try {
            foreach (TrainingModality::all() as $modality) {
                $title = 'Demo • '.$modality->name;
                if (Workout::where('title', $title)->exists()) {
                    continue;
                }
                app(SaveWorkout::class)->handle($user, [
                    'training_modality_id' => $modality->id, 'title' => $title, 'structure' => 'main',
                    'scheduled_for' => now($user->box->timezone)->toDateString(), 'status' => 'published',
                    'blocks' => [[
                        'type' => 'workout', 'title' => 'Engine day', 'description' => "4 rounds\n200 m corrida\n12 agachamentos\n8 burpees",
                        'timer_type' => $modality->slug === 'functional' ? 'amrap' : 'for_time', 'duration_seconds' => 900,
                        'timer_settings' => ['preparation_seconds' => 10],
                    ]],
                ]);
            }
        } finally {
            $context->clear();
        }
    }
}
