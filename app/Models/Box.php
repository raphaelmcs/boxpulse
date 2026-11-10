<?php

namespace App\Models;

use Database\Factories\BoxFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'head_coach_name', 'timezone', 'primary_color', 'secondary_color', 'logo_path'])]
class Box extends Model
{
    /** @use HasFactory<BoxFactory> */
    use HasFactory;

    public function modalities(): BelongsToMany
    {
        return $this->belongsToMany(TrainingModality::class, 'box_modalities');
    }

    public function offersModality(int $id): bool
    {
        return $this->modalities()->where('training_modalities.id', $id)->exists();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function workouts(): HasMany
    {
        return $this->hasMany(Workout::class);
    }
}
