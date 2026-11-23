<?php

namespace App\Actions;

use App\Models\WorkoutSession;
use App\Timers\SessionState;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ReadBoxTvState
{
    public function __construct(private SessionState $state) {}

    public function handle(CarbonImmutable $now): ?WorkoutSession
    {
        $session = DB::transaction(function (): ?WorkoutSession {
            $latest = WorkoutSession::query()->whereNotNull('started_at')->latest('started_at')->latest('id')->first();

            return $latest?->status === 'active' ? $latest->load('timers') : $latest;
        }, 3);
        if ($session?->status !== 'active') {
            return $session;
        }

        $timer = $session->timers->firstWhere('position', $session->current_position);
        if (! $timer || ! $this->state->needsSynchronization($timer, $now)) {
            return $session;
        }

        return DB::transaction(function () use ($session): ?WorkoutSession {
            $locked = WorkoutSession::whereKey($session->id)->lockForUpdate()->first();

            return $locked && $locked->status === 'active'
                ? $this->state->synchronize($locked, CarbonImmutable::now('UTC'))
                : $locked;
        }, 3);
    }
}
