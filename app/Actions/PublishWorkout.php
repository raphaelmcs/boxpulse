<?php

namespace App\Actions;

use App\Models\User;
use App\Models\Workout;
use App\Workouts\BoxModalities;
use App\Workouts\WorkoutDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PublishWorkout
{
    public function handle(User $actor, Workout $workout, string $status): Workout
    {
        Gate::forUser($actor)->authorize('update', $workout);

        return DB::transaction(function () use ($actor, $workout, $status): Workout {
            $box = BoxModalities::lock($actor);
            $locked = Workout::whereKey($workout->id)->lockForUpdate()->firstOrFail()->load('blocks.stations');
            if ($status === 'published') {
                BoxModalities::ensureEnabled($box, $locked->training_modality_id);
                app(WorkoutDefinition::class)->validate([...app(WorkoutDefinition::class)->fromWorkout($locked), 'status' => 'published']);
            }
            $locked->update(['status' => $status]);

            return $locked;
        }, 3);
    }
}
