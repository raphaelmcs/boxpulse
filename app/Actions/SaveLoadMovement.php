<?php

namespace App\Actions;

use App\Models\LoadMovement;
use App\Models\User;
use App\Workouts\BoxModalities;
use App\Workouts\LoadLibrary;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveLoadMovement
{
    public function handle(User $actor, LoadMovement $movement, array $data): LoadMovement
    {
        abort_unless($actor->role === 'admin' && (! $movement->exists || $movement->box_id === $actor->box_id), 403);
        $data['normalized_name'] = LoadLibrary::key($data['name']);
        if (! preg_match('/[a-z0-9]/', $data['normalized_name']) || mb_strlen($data['normalized_name']) > 120) {
            throw ValidationException::withMessages(['name' => 'Informe um nome de movimento reconhecível.']);
        }

        return DB::transaction(function () use ($actor, $movement, $data): LoadMovement {
            BoxModalities::lock($actor);
            $duplicate = LoadMovement::where('equipment', $data['equipment'])->where('normalized_name', $data['normalized_name'])
                ->when($movement->exists, fn ($query) => $query->whereKeyNot($movement->id))->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['name' => 'Já existe um movimento com esse nome para o equipamento.']);
            }
            $movement->fill($data)->save();

            return $movement;
        }, 3);
    }
}
