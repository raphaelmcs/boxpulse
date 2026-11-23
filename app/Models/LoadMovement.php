<?php

namespace App\Models;

use Database\Factories\LoadMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable(['name', 'normalized_name', 'equipment', 'variation', 'modalities', 'aliases', 'active'])]
class LoadMovement extends TenantModel
{
    /** @use HasFactory<LoadMovementFactory> */
    use HasFactory;

    protected $attributes = ['active' => true];

    protected function casts(): array
    {
        return ['modalities' => 'array', 'aliases' => 'array', 'active' => 'boolean'];
    }
}
