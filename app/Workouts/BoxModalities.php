<?php

namespace App\Workouts;

use App\Models\Box;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class BoxModalities
{
    public static function lock(User $actor): Box
    {
        return Box::whereKey($actor->box_id)->lockForUpdate()->firstOrFail();
    }

    public static function ensureEnabled(Box $box, int $modality): void
    {
        if (! $box->offersModality($modality)) {
            throw ValidationException::withMessages(['training_modality_id' => 'Modalidade desativada. Reative-a nas Configurações da Box para criar, publicar, copiar ou preparar uma nova aula.']);
        }
    }
}
