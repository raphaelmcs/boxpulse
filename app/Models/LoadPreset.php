<?php

namespace App\Models;

use Database\Factories\LoadPresetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['equipment', 'movement', 'movement_id', 'name', 'context', 'notes', 'levels', 'unit', 'reference', 'active'])]
class LoadPreset extends TenantModel
{
    /** @use HasFactory<LoadPresetFactory> */
    use HasFactory;

    protected $attributes = ['unit' => 'kg', 'active' => true];

    protected function casts(): array
    {
        return ['levels' => 'array', 'active' => 'boolean'];
    }

    public function catalogMovement(): BelongsTo
    {
        return $this->belongsTo(LoadMovement::class, 'movement_id');
    }
}
