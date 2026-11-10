<?php

namespace App\Models;

use Database\Factories\WorkoutSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkoutSession extends TenantModel
{
    /** @use HasFactory<WorkoutSessionFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'active', 'current_position' => 0, 'version' => 1, 'auto_advance' => false];

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected function casts(): array
    {
        return ['auto_advance' => 'boolean', 'version' => 'integer', 'workout_version' => 'integer', 'current_position' => 'integer', 'scheduled_for' => 'immutable_date', 'started_at' => 'immutable_datetime', 'finished_at' => 'immutable_datetime', 'controller_lease_expires_at' => 'immutable_datetime'];
    }

    public function hasExecutionActivity(): bool
    {
        if ($this->started_at !== null || $this->current_position !== 0) {
            return true;
        }
        if ($this->relationLoaded('timers')) {
            return $this->timers->contains(fn ($timer) => $timer->state !== 'idle' || $timer->elapsed_ms !== 0 || $timer->started_at !== null);
        }

        return $this->timers()->where(function ($query): void {
            $query->where('state', '!=', 'idle')->orWhere('elapsed_ms', '!=', 0)->orWhereNotNull('started_at');
        })->exists();
    }

    public function timers(): HasMany
    {
        return $this->hasMany(TimerSession::class)->orderBy('position');
    }

    public function commands(): HasMany
    {
        return $this->hasMany(SessionCommand::class);
    }

    public function workout(): BelongsTo
    {
        return $this->belongsTo(Workout::class);
    }
}
