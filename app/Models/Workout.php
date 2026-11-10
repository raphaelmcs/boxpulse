<?php

namespace App\Models;

use Database\Factories\WorkoutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['training_modality_id', 'title', 'scheduled_for', 'status', 'structure'])]
class Workout extends TenantModel
{
    /** @use HasFactory<WorkoutFactory> */
    use HasFactory;

    protected $attributes = ['content_version' => 1];

    protected function casts(): array
    {
        return ['scheduled_for' => 'immutable_date', 'content_version' => 'integer'];
    }

    public function modality(): BelongsTo
    {
        return $this->belongsTo(TrainingModality::class, 'training_modality_id');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(WorkoutBlock::class)->orderBy('position');
    }

    public function activeSession(): HasOne
    {
        return $this->hasOne(WorkoutSession::class)->whereNotNull('active_workout_id');
    }

    public function latestSession(): HasOne
    {
        return $this->hasOne(WorkoutSession::class)->latestOfMany();
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
