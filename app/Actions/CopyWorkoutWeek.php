<?php

namespace App\Actions;

use App\Models\Box;
use App\Models\User;
use App\Models\Workout;
use App\Tenancy\CurrentBox;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CopyWorkoutWeek
{
    public function handle(User $actor, array $data): int
    {
        Gate::forUser($actor)->authorize('create', Workout::class);
        $source = CarbonImmutable::parse($data['source_date'])->startOfWeek(CarbonImmutable::MONDAY);
        $target = CarbonImmutable::parse($data['target_date'])->startOfWeek(CarbonImmutable::MONDAY);
        if ($source->equalTo($target)) {
            throw ValidationException::withMessages(['target_date' => 'Escolha uma semana diferente da origem.']);
        }

        return DB::transaction(function () use ($actor, $data, $source, $target): int {
            Box::whereKey(app(CurrentBox::class)->requireId())->lockForUpdate()->firstOrFail();
            if ($data['conflict_policy'] === 'abort' && Workout::whereBetween('scheduled_for', [$target->toDateString(), $target->addDays(6)->toDateString()])->exists()) {
                throw ValidationException::withMessages(['target_date' => 'A semana de destino já contém treinos. Nada foi copiado. Escolha adicionar como rascunhos para preservar os existentes.']);
            }
            $workouts = Workout::whereBetween('scheduled_for', [$source->toDateString(), $source->addDays(6)->toDateString()])->whereIn('training_modality_id', $actor->box->modalities()->select('training_modalities.id'))->orderBy('id')->lockForUpdate()->get();
            if ($workouts->isEmpty()) {
                throw ValidationException::withMessages(['source_date' => 'Não há treinos de modalidades ativas nesta semana para copiar.']);
            }
            foreach ($workouts as $workout) {
                $offset = (int) $source->diffInDays(CarbonImmutable::parse($workout->scheduled_for->toDateString()));
                app(DuplicateWorkout::class)->handle($actor, $workout, $target->addDays($offset)->toDateString());
            }

            return $workouts->count();
        }, 3);
    }
}
