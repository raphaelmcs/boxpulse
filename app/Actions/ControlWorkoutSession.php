<?php

namespace App\Actions;

use App\Models\SessionCommand;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Timers\SessionState;
use App\Workouts\BoxModalities;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ControlWorkoutSession
{
    public function __construct(private SessionState $state) {}

    /** @return array{session: WorkoutSession, conflict: ?string} */
    public function claim(User $actor, WorkoutSession $session, string $client, bool $takeover): array
    {
        Gate::forUser($actor)->authorize('control', $session);

        return DB::transaction(function () use ($actor, $session, $client, $takeover): array {
            $locked = WorkoutSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            $now = CarbonImmutable::now('UTC');
            $this->state->synchronize($locked, $now);
            if ($locked->status !== 'active') {
                return $this->result($locked, 'Esta aula já foi encerrada.');
            }
            if ($locked->controller_client_id !== $client && $locked->controller_lease_expires_at?->greaterThan($now) && ! $takeover) {
                return $this->result($locked, 'Outra aba controla esta aula. Use Assumir controle para transferir.');
            }
            if ($locked->controller_client_id !== $client) {
                $locked->version++;
            }
            $locked->controller_client_id = $client;
            $locked->controller_user_id = $actor->id;
            $locked->controller_lease_expires_at = $now->addSeconds(config('boxpulse.controller_lease_seconds'));
            $locked->save();

            return $this->result($locked);
        }, 3);
    }

    /** @return array{session: WorkoutSession, conflict: ?string} */
    public function handle(User $actor, WorkoutSession $session, array $data): array
    {
        Gate::forUser($actor)->authorize('control', $session);
        $hash = hash('sha256', json_encode([$actor->id, $data['client_id'], $data['action'], (int) $data['expected_version'], (bool) ($data['confirmation'] ?? false)], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $session, $data, $hash): array {
            BoxModalities::lock($actor);
            $locked = WorkoutSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            $now = CarbonImmutable::now('UTC');
            $this->state->synchronize($locked, $now);
            $existing = $locked->commands()->where('command_id', $data['command_id'])->first();
            if ($existing) {
                return $this->result($locked, hash_equals($existing->request_hash, $hash) ? null : 'Identificador de comando já utilizado com outros dados.');
            }
            if ($locked->status !== 'active') {
                return $this->result($locked, 'A aula já foi encerrada. Prepare uma nova sessão para recomeçar.');
            }
            if ($locked->controller_client_id !== $data['client_id'] || (int) $locked->controller_user_id !== $actor->id || ! $locked->controller_lease_expires_at?->greaterThan($now)) {
                return $this->result($locked, 'O controle desta aba expirou ou foi transferido.');
            }
            if ($locked->version !== (int) $data['expected_version']) {
                return $this->result($locked, 'O estado foi atualizado em outra operação. Confira a tela e tente novamente.');
            }
            $timer = $locked->timers()->where('position', $locked->current_position)->firstOrFail();
            $action = $data['action'];
            if (in_array($action, ['start', 'resume', 'next', 'previous', 'complete_block'], true) && ! $locked->hasExecutionActivity() && ! $actor->box->offersModality($locked->workout->training_modality_id)) {
                return $this->result($locked, 'Modalidade desativada. Reative-a antes de iniciar esta aula pendente.');
            }
            if (in_array($action, ['start', 'resume', 'next', 'previous', 'complete_block'], true) && ! $locked->hasExecutionActivity()
                && $locked->workout->status === 'published' && $locked->workout_version !== $locked->workout->content_version) {
                return $this->result($locked, 'O treino foi atualizado. Prepare a versão publicada atual antes de iniciar esta aula pendente.');
            }
            if ($action === 'start' || $action === 'resume') {
                $this->requireState($timer->state, $action === 'start' ? ['idle'] : ['paused']);
                $this->state->start($timer, $now);
                $locked->started_at ??= $now;
            } elseif ($action === 'pause') {
                $this->requireState($timer->state, ['preparing', 'running']);
                $this->state->pause($timer, $now);
            } elseif ($action === 'reset') {
                if (! ($data['confirmation'] ?? false)) {
                    throw ValidationException::withMessages(['confirmation' => 'Confirme o reinício do timer.']);
                }
                $timer->state = 'idle';
                $timer->elapsed_ms = 0;
                $timer->started_at = null;
                $timer->effective_started_at = null;
                $timer->paused_at = null;
                $timer->finished_at = null;
                $timer->save();
            } elseif ($action === 'complete_block') {
                $this->requireState($timer->state, ['idle', 'preparing', 'running', 'paused']);
                $this->state->complete($timer, $now);
            } elseif (in_array($action, ['next', 'previous'], true)) {
                $position = $locked->current_position + ($action === 'next' ? 1 : -1);
                if (! $locked->timers()->where('position', $position)->exists()) {
                    throw ValidationException::withMessages(['action' => 'Não existe outra etapa nesta direção.']);
                }
                if ($action === 'next' && $timer->state !== 'completed') {
                    $this->state->complete($timer, $now);
                } elseif ($action === 'previous' && in_array($timer->state, ['preparing', 'running'], true)) {
                    $this->state->pause($timer, $now);
                }
                $locked->current_position = $position;
            } elseif (in_array($action, ['finish', 'cancel'], true)) {
                if (! in_array($timer->state, ['completed', 'cancelled'], true)) {
                    $this->state->complete($timer, $now, $action === 'cancel' ? 'cancelled' : 'completed');
                }
                $locked->status = $action === 'cancel' ? 'cancelled' : 'completed';
                $locked->active_workout_id = null;
                $locked->finished_at = $now;
                $locked->controller_lease_expires_at = null;
            } else {
                throw ValidationException::withMessages(['action' => 'Comando inválido.']);
            }
            $locked->version++;
            $locked->save();
            $command = new SessionCommand;
            $command->workout_session_id = $locked->id;
            $command->command_id = $data['command_id'];
            $command->action = $action;
            $command->request_hash = $hash;
            $command->result_version = $locked->version;
            $command->save();
            Log::info('workout_session.command', ['box_id' => $locked->box_id, 'session_id' => $locked->id, 'user_id' => $actor->id, 'action' => $action, 'version' => $locked->version]);

            return $this->result($locked);
        }, 3);
    }

    private function requireState(string $state, array $allowed): void
    {
        if (! in_array($state, $allowed, true)) {
            throw ValidationException::withMessages(['action' => 'Comando indisponível no estado atual.']);
        }
    }

    private function result(WorkoutSession $session, ?string $conflict = null): array
    {
        return ['session' => $session->load('timers'), 'conflict' => $conflict];
    }
}
