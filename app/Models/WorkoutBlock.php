<?php

namespace App\Models;

use Database\Factories\WorkoutBlockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['type', 'position', 'title', 'description', 'notes', 'timer_type', 'timer_settings', 'duration_seconds', 'suggested_loads', 'text_style', 'load_entries'])]
class WorkoutBlock extends TenantModel
{
    /** @use HasFactory<WorkoutBlockFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['text_style' => 'array', 'load_entries' => 'array', 'suggested_loads' => 'array', 'timer_settings' => 'array', 'duration_seconds' => 'integer'];
    }

    public function workout(): BelongsTo
    {
        return $this->belongsTo(Workout::class);
    }

    public function stations(): HasMany
    {
        return $this->hasMany(WorkoutStation::class)->orderBy('position');
    }
}
