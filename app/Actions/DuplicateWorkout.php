<?php

namespace App\Actions;

use App\Models\User;
use App\Models\Workout;
use App\Workouts\BoxModalities;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DuplicateWorkout
{
    public function handle(User $actor, Workout $workout, string $date): Workout
    {
        Gate::forUser($actor)->authorize('update', $workout);

        return DB::transaction(function () use ($actor, $workout, $date): Workout {
            $box = BoxModalities::lock($actor);
            $source = Workout::whereKey($workout->id)->lockForUpdate()->firstOrFail()->load('blocks.stations');
            BoxModalities::ensureEnabled($box, $source->training_modality_id);
            $copy = $source->replicate(['editor_draft', 'editor_create_id', 'editor_write_id', 'editor_write_hash']);
            $copy->scheduled_for = $date;
            $copy->status = 'draft';
            $copy->content_version = 1;
            $copy->edit_version = 1;
            $copy->created_by = $actor->id;
            $copy->save();
            foreach ($source->blocks as $block) {
                $newBlock = $block->replicate();
                $newBlock->workout_id = $copy->id;
                $newBlock->save();
                foreach ($block->stations as $station) {
                    $newStation = $station->replicate();
                    $newStation->workout_block_id = $newBlock->id;
                    $newStation->save();
                }
            }

            return $copy;
        }, 3);
    }
}
