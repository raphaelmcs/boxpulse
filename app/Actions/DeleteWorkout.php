<?php

namespace App\Actions;

use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeleteWorkout
{
    public function handle(User $actor, Workout $workout): void
    {
        Gate::forUser($actor)->authorize('delete', $workout);
        DB::transaction(function () use ($workout): void {
            $locked = Workout::whereKey($workout->id)->lockForUpdate()->firstOrFail();
            if (WorkoutSession::where('workout_id', $locked->id)->exists()) {
                throw ValidationException::withMessages(['workout' => 'Este treino possui histórico de aulas e não pode ser excluído. Você pode editá-lo sem alterar as sessões existentes.']);
            }
            $this->deleteBlocks($locked);
            $locked->delete();
        }, 3);
    }

    public function deleteBlocks(Workout $workout): void
    {
        foreach ($workout->blocks()->with('stations')->get() as $block) {
            foreach ($block->stations as $station) {
                $station->delete();
            }
            $block->delete();
        }
    }
}
