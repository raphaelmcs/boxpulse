<?php

namespace App\Timers;

use App\Models\TimerSession;
use App\Models\WorkoutSession;
use Carbon\CarbonImmutable;

class SessionState
{
    public function elapsed(TimerSession $timer, CarbonImmutable $now): int
    {
        $elapsed = $timer->elapsed_ms;
        if (in_array($timer->state, ['preparing', 'running'], true) && $timer->effective_started_at) {
            $elapsed += max(0, (int) ($now->getTimestampMs() - $timer->effective_started_at->getTimestampMs()));
        }

        return $elapsed;
    }

    /** Must run while holding a lock on the parent session. */
    public function synchronize(WorkoutSession $session, CarbonImmutable $now): WorkoutSession
    {
        if ($session->status !== 'active') {
            return $session->load('timers');
        }
        $changed = false;
        for ($step = 0; $step < 4; $step++) {
            $timedCompletion = false;
            $timer = $session->timers()->where('position', $session->current_position)->firstOrFail();
            $elapsed = $this->elapsed($timer, $now);
            $preparation = $timer->config['preparation_ms'];
            $limit = $timer->config['limit_ms'];
            if ($timer->state === 'preparing' && $elapsed >= $preparation) {
                $timer->state = 'running';
                $changed = true;
            }
            if ($timer->state === 'running' && $limit !== null && $elapsed >= $preparation + $limit) {
                $timer->state = 'completed';
                $timer->elapsed_ms = $preparation + $limit;
                $timer->finished_at = $now->subMilliseconds($elapsed - $timer->elapsed_ms);
                $timer->effective_started_at = null;
                $timedCompletion = true;
                $changed = true;
            }
            if ($timer->isDirty()) {
                $timer->save();
            }
            if (! $timedCompletion || ! $session->auto_advance) {
                break;
            }
            $next = $session->timers()->where('position', $session->current_position + 1)->first();
            if (! $next) {
                $session->status = 'completed';
                $session->active_workout_id = null;
                $session->finished_at = $timer->finished_at;
                $changed = true;
                break;
            }
            $session->current_position++;
            $this->start($next, $timer->finished_at ?? $now);
            $changed = true;
        }
        if ($changed) {
            $session->version++;
            $session->save();
        }

        return $session->load('timers');
    }

    public function start(TimerSession $timer, CarbonImmutable $now): void
    {
        $timer->state = $timer->elapsed_ms < $timer->config['preparation_ms'] ? 'preparing' : 'running';
        $timer->started_at ??= $now;
        $timer->effective_started_at = $now;
        $timer->paused_at = null;
        $timer->save();
    }

    public function pause(TimerSession $timer, CarbonImmutable $now): void
    {
        $timer->elapsed_ms = $this->elapsed($timer, $now);
        $timer->effective_started_at = null;
        $timer->state = 'paused';
        $timer->paused_at = $now;
        $timer->save();
    }

    public function complete(TimerSession $timer, CarbonImmutable $now, string $state = 'completed'): void
    {
        $timer->elapsed_ms = $this->elapsed($timer, $now);
        $timer->effective_started_at = null;
        $timer->state = $state;
        $timer->finished_at = $now;
        $timer->save();
    }
}
