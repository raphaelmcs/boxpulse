<?php

namespace App\Models;

use Database\Factories\WorkoutStationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['position', 'name', 'description', 'distance', 'repetitions', 'load', 'duration_seconds', 'rest_seconds', 'transition_seconds'])]
class WorkoutStation extends TenantModel
{
    /** @use HasFactory<WorkoutStationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['distance' => 'decimal:2', 'load' => 'decimal:2', 'repetitions' => 'integer', 'duration_seconds' => 'integer', 'rest_seconds' => 'integer', 'transition_seconds' => 'integer'];
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(WorkoutBlock::class, 'workout_block_id');
    }
}
