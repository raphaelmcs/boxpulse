<?php

namespace App\Models;

use Database\Factories\TimerSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimerSession extends TenantModel
{
    /** @use HasFactory<TimerSessionFactory> */
    use HasFactory;

    protected $attributes = ['state' => 'idle', 'elapsed_ms' => 0];

    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected function casts(): array
    {
        return ['suggested_loads' => 'array', 'config' => 'array', 'stations_snapshot' => 'array', 'elapsed_ms' => 'integer', 'position' => 'integer', 'started_at' => 'immutable_datetime', 'effective_started_at' => 'immutable_datetime', 'paused_at' => 'immutable_datetime', 'finished_at' => 'immutable_datetime'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WorkoutSession::class, 'workout_session_id');
    }
}
