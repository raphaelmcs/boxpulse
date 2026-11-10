<?php

namespace App\Actions;

use App\Models\TimerSession;
use App\Models\User;
use App\Models\Workout;
use App\Models\WorkoutSession;
use App\Timers\CompileTimer;
use App\Timers\SessionState;
use App\Workouts\BoxModalities;
use App\Workouts\WorkoutDefinition;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PrepareWorkoutSession
{
    public function handle(User $actor, Workout $workout, array $options, ?WorkoutSession $restart = null): WorkoutSession
    {
        Gate::forUser($actor)->authorize('update', $workout);

        return DB::transaction(function () use ($actor, $workout, $options, $restart): WorkoutSession {
            $box = BoxModalities::lock($actor);
            $locked = Workout::whereKey($workout->id)->lockForUpdate()->firstOrFail()->load('modality', 'blocks.stations');
            if ($locked->status !== 'published') {
                throw ValidationException::withMessages(['workout' => 'Publique o treino antes de iniciar uma apresentação.']);
            }
            $existing = WorkoutSession::where('active_workout_id', $locked->id)->lockForUpdate()->first();
            if (! $box->offersModality($locked->training_modality_id)) {
                if ($existing && $existing->hasExecutionActivity() && ! $restart) {
                    return $existing;
                }
                BoxModalities::ensureEnabled($box, $locked->training_modality_id);
            }
            $outdated = $existing && $existing->workout_version !== $locked->content_version;
            if ($restart) {
                if (! $existing || $existing->id !== $restart->id) {
                    throw ValidationException::withMessages(['session' => 'Esta aula já foi encerrada ou substituída. Abra a sessão atual.']);
                }
                Gate::forUser($actor)->authorize('control', $existing);
                $now = CarbonImmutable::now('UTC');
                if (! ($options['confirmation'] ?? false)
                    || $existing->version !== (int) ($options['expected_version'] ?? 0)
                    || $locked->content_version !== (int) ($options['expected_workout_version'] ?? 0)
                    || $existing->controller_client_id !== ($options['client_id'] ?? null)
                    || (int) $existing->controller_user_id !== $actor->id
                    || ! $existing->controller_lease_expires_at?->greaterThan($now)) {
                    throw ValidationException::withMessages(['session' => 'O estado, a versão publicada ou o controle mudou. Confira a tela e confirme novamente.']);
                }
                if (! $outdated) {
                    throw ValidationException::withMessages(['session' => 'Esta aula já utiliza a versão atual do treino.']);
                }
                $options['preparation_seconds'] = $existing->preparation_override;
                $options['auto_advance'] = $existing->auto_advance;
            } elseif ($existing && (! $outdated || $existing->hasExecutionActivity() || ! $actor->can('control', $existing))) {
                return $existing;
            }
            if (! $existing) {
                $last = WorkoutSession::where('workout_id', $locked->id)->orderByDesc('id')->first();
                if ($last && (! ($options['new_session_confirmation'] ?? false) || (int) ($options['previous_session_id'] ?? 0) !== $last->id)) {
                    throw ValidationException::withMessages(['new_session_confirmation' => 'Esta aula já tem uma sessão encerrada. Atualize a lista e confirme a criação de uma nova sessão.']);
                }
            }
            $definition = app(WorkoutDefinition::class);
            $data = $definition->validate($definition->fromWorkout($locked));
            if ($existing) {
                $this->supersede($existing);
            }
            $session = new WorkoutSession;
            $session->workout_id = $locked->id;
            $session->workout_version = $locked->content_version;
            $session->active_workout_id = $locked->id;
            $session->responsible_user_id = $actor->id;
            $session->title = $locked->title;
            $session->modality_name = $locked->modality->name;
            $session->scheduled_for = $locked->scheduled_for;
            $session->auto_advance = (bool) ($options['auto_advance'] ?? false);
            $session->preparation_override = $options['preparation_seconds'] ?? null;
            $session->save();
            foreach ($data['blocks'] as $position => $block) {
                $timer = new TimerSession;
                $timer->workout_session_id = $session->id;
                $timer->position = $position;
                $timer->block_type = $block['type'];
                $timer->title = $block['title'] ?? null;
                $timer->description = $block['description'] ?? null;
                $timer->notes = $block['notes'] ?? null;
                $timer->timer_type = $block['timer_type'];
                $timer->suggested_loads = $block['suggested_loads'] ?? null;
                $timer->stations_snapshot = $block['stations'] ?? [];
                $timer->config = app(CompileTimer::class)->compile($block, $session->preparation_override);
                $timer->save();
            }

            if ($existing) {
                $existing->superseded_by_id = $session->id;
                $existing->save();
            }

            return $session;
        }, 3);
    }

    private function supersede(WorkoutSession $session): void
    {
        $now = CarbonImmutable::now('UTC');
        $state = app(SessionState::class);
        $state->synchronize($session, $now);
        if ($session->status !== 'active') {
            throw ValidationException::withMessages(['session' => 'A aula acabou de encerrar. Prepare uma nova apresentação a partir do treino atualizado.']);
        }
        $timer = $session->timers()->where('position', $session->current_position)->firstOrFail();
        if (in_array($timer->state, ['preparing', 'running', 'paused'], true)) {
            $state->complete($timer, $now, 'cancelled');
        }
        $session->status = 'cancelled';
        $session->active_workout_id = null;
        $session->finished_at = $now;
        $session->controller_lease_expires_at = null;
        $session->version++;
        $session->save();
    }
}
