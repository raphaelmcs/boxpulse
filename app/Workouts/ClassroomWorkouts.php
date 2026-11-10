<?php

namespace App\Workouts;

use App\Models\Box;
use App\Models\Workout;
use App\Models\WorkoutSession;
use App\Timers\SessionState;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ClassroomWorkouts
{
    public function snapshot(WorkoutSession $session): WorkoutSession
    {
        return DB::transaction(function () use ($session): WorkoutSession {
            $locked = WorkoutSession::whereKey($session->id)->lockForUpdate()->firstOrFail();

            return app(SessionState::class)->synchronize($locked, CarbonImmutable::now('UTC'));
        }, 3);
    }

    public function activeSessions(): Collection
    {
        return WorkoutSession::where('status', 'active')->orderByDesc('id')->get()
            ->map(fn ($session) => $this->snapshot($session))
            ->filter(fn ($session) => $session->status === 'active')
            ->each(fn ($session) => $session->load('workout.modality'));
    }

    public function today(Box $box): Builder
    {
        return Workout::whereDate('scheduled_for', now($box->timezone)->toDateString())
            ->whereIn('training_modality_id', $box->modalities()->select('training_modalities.id'))
            ->where('status', 'published');
    }
}
