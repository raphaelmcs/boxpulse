<?php

namespace Tests\Feature;

use App\Models\TrainingModality;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Tenancy\CurrentBox;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithSessions;
use Tests\TestCase;

class ClassroomTest extends TestCase
{
    use InteractsWithSessions, RefreshDatabase;

    public function test_today_lists_only_published_enabled_workouts_on_the_civil_date_of_the_box(): void
    {
        $this->travelTo(Carbon::parse('2026-11-11 01:30:00', 'UTC'));
        $user = User::factory()->create();
        $today = $this->createWorkoutFor($user, [...$this->workoutInput('crossfit', 'full'), 'title' => 'Aula de terça']);
        $this->createWorkoutFor($user, [...$this->workoutInput(), 'title' => 'Rascunho oculto', 'status' => 'draft']);
        $this->createWorkoutFor($user, [...$this->workoutInput(), 'title' => 'Aula de quarta', 'scheduled_for' => '2026-11-11']);
        $this->createWorkoutFor($user, [...$this->workoutInput('hyrox'), 'title' => 'Modalidade desativada']);
        $user->box->modalities()->sync(TrainingModality::where('slug', 'crossfit')->pluck('id')->all());
        $foreign = User::factory()->create();
        $this->createWorkoutFor($foreign, [...$this->workoutInput(), 'title' => 'Aula de outra box']);
        $response = $this->actingAs($user)->get(route('coach.today'))->assertOk()->assertSee('10.11.2026')->assertSee('terça-feira')->assertSee($today->title)->assertSee('TC 15 min')->assertSee('3 blocos')->assertSee('Preparar aula')->assertSee('data-box-clock', false)->assertDontSee('Rascunho oculto')->assertDontSee('Aula de quarta')->assertDontSee('Modalidade desativada')->assertDontSee('Aula de outra box')->assertDontSee('classroom-filters')->assertDontSee('workspace-nav')->assertDontSee('Configurações da Box');
        $this->assertSame([$today->id], $response->viewData('workouts')->modelKeys());
    }

    public function test_multiple_active_modalities_can_be_filtered_and_inactive_filter_is_rejected(): void
    {
        $user = User::factory()->create();
        $date = now($user->box->timezone)->toDateString();
        $crossfit = $this->createWorkoutFor($user, [...$this->workoutInput(), 'scheduled_for' => $date]);
        $hyrox = $this->createWorkoutFor($user, [...$this->workoutInput('hyrox'), 'scheduled_for' => $date]);
        $user->box->modalities()->sync(TrainingModality::whereIn('slug', ['crossfit', 'hyrox'])->pluck('id')->all());
        $this->actingAs($user)->get(route('coach.today'))->assertOk()->assertSee('Todas')->assertSee($crossfit->title)->assertSee($hyrox->title)->assertDontSee('Treinamento Funcional');
        $this->get(route('coach.today', ['modality' => 'hyrox']))->assertOk()->assertSee($hyrox->title)->assertDontSee($crossfit->title);
        $this->getJson(route('coach.today', ['modality' => 'functional']))->assertUnprocessable()->assertJsonValidationErrors('modality');
        $this->getJson(route('coach.today', ['modality' => ['invalid']]))->assertUnprocessable();
    }

    public function test_empty_classroom_offers_management_links_without_an_empty_screen(): void
    {
        $this->actingAs(User::factory()->create())->get(route('coach.today'))->assertOk()->assertSee('Nenhuma aula programada para hoje.')->assertSee('Consultar programação')->assertSee('Cadastrar novo treino')->assertSee(route('workspace'), false);
    }

    public static function preparationChoices(): array
    {
        return [
            'per stage' => ['coach.prepare', ['preparation_seconds' => ''], null, false, [0, 3000, 10000]],
            'no countdown' => ['coach.prepare', ['preparation_seconds' => '0', 'auto_advance' => '1'], 0, true, [0, 0, 0]],
            'ten seconds' => ['sessions.store', ['preparation_seconds' => '10', 'auto_advance' => '0'], 10, false, [0, 10000, 10000]],
            'twenty seconds' => ['coach.prepare', ['preparation_seconds' => '20', 'auto_advance' => '1'], 20, true, [0, 20000, 20000]],
            'thirty seconds' => ['sessions.store', ['preparation_seconds' => '30', 'auto_advance' => '0'], 30, false, [0, 30000, 30000]],
            'custom countdown' => ['coach.prepare', ['preparation_seconds' => 'custom', 'custom_preparation_seconds' => '45'], 45, false, [0, 45000, 45000]],
        ];
    }

    #[DataProvider('preparationChoices')]
    public function test_preparation_options_apply_once_to_the_new_session(string $endpoint, array $options, ?int $override, bool $autoAdvance, array $milliseconds): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 12:00:00', 'UTC'));
        $user = User::factory()->create();
        $input = $this->workoutInput('crossfit', 'full');
        $input['blocks'][1]['timer_settings']['preparation_seconds'] = 3;
        $workout = $this->createWorkoutFor($user, $input);
        $this->actingAs($user);
        foreach ([route('coach.today'), route('workouts.presentation', $workout)] as $url) {
            $this->get($url)->assertOk()->assertSee('Sem contagem')->assertSee('10 segundos — padrão')
                ->assertSee('20 segundos')->assertSee('30 segundos')->assertSee('Personalizar')
                ->assertSee('Avançar automaticamente para a próxima etapa ao concluir o cronômetro.')
                ->assertDontSee('name="auto_advance" value="1" checked', false);
        }
        $this->post(route($endpoint, $workout), $options)->assertSessionHasNoErrors()->assertRedirect();
        $session = $this->get(route('sessions.index'))->viewData('sessions')->sole();
        $this->assertSame($override, $session->preparation_override);
        $this->getJson(route('sessions.state', $session))->assertOk()
            ->assertJsonPath('data.auto_advance', $autoAdvance)
            ->assertJsonPath('data.timers.0.config.preparation_ms', $milliseconds[0])
            ->assertJsonPath('data.timers.1.config.preparation_ms', $milliseconds[1])
            ->assertJsonPath('data.timers.2.config.preparation_ms', $milliseconds[2])
            ->assertJsonPath('data.timers.0.state', 'idle')
            ->assertJsonPath('data.controller_client_id', null);
        $this->get(route('coach.today'))->assertOk()->assertSee('Continuar preparação')->assertDontSee('data-preparation-options', false);
        $this->post(route('coach.prepare', $workout), ['preparation_seconds' => 60, 'auto_advance' => ! $autoAdvance])
            ->assertRedirect(route('coach.ready', $session));
        $this->assertDatabaseHas('workout_sessions', ['id' => $session->id, 'preparation_override' => $override, 'auto_advance' => $autoAdvance, 'version' => 1]);
        $this->assertDatabaseHas('workouts', ['id' => $workout->id, 'presentation_options' => null]);
        $this->assertDatabaseCount('workout_sessions', 1);
    }

    public function test_custom_preparation_is_validated_and_legacy_preferences_are_preselected(): void
    {
        $user = User::factory()->create();
        $input = [...$this->workoutInput(), 'presentation_options' => ['preparation_seconds' => 7, 'auto_advance' => true]];
        $workout = $this->createWorkoutFor($user, $input);
        $this->actingAs($user)->get(route('workouts.presentation', $workout))->assertOk()
            ->assertSee('value="custom" selected', false)->assertSee('step="1" value="7"', false)
            ->assertSee('name="auto_advance" value="1" checked', false);
        foreach ([null, -1, 61, 2.5] as $custom) {
            $this->postJson(route('coach.prepare', $workout), ['preparation_seconds' => 'custom', 'custom_preparation_seconds' => $custom])
                ->assertUnprocessable()->assertJsonValidationErrors('custom_preparation_seconds');
        }
        $this->assertDatabaseCount('workout_sessions', 0);
        $this->post(route('sessions.store', $workout), ['preparation_seconds' => 'custom', 'custom_preparation_seconds' => 7, 'auto_advance' => '1'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('workout_sessions', ['workout_id' => $workout->id, 'preparation_override' => 7, 'auto_advance' => true]);
    }

    public function test_prepare_ready_and_tv_share_one_idle_snapshot_and_repeated_clicks_do_not_duplicate(): void
    {
        $user = User::factory()->create();
        $input = $this->workoutInput('crossfit', 'full');
        $input['blocks'][2]['suggested_loads'] = ['equipment' => 'DB', 'rx' => '22/16', 'scale' => '18/12'];
        $workout = $this->createWorkoutFor($user, $input);
        $this->actingAs($user)->post(route('coach.prepare', $workout))->assertSessionHasNoErrors()->assertRedirect();
        $session = $this->get(route('sessions.index'))->viewData('sessions')->first();
        $this->post(route('coach.prepare', $workout))->assertRedirect(route('coach.ready', $session));
        $this->post(route('sessions.store', $workout))->assertRedirect(route('sessions.control', $session));
        $this->get(route('coach.ready', $session))->assertOk()->assertSee('Tudo pronto para a aula.')->assertSee('22/16')->assertSee('18/12')->assertSee('Abrir na TV')->assertSee('Ir para controle da aula')->assertSee('target="_blank"', false)->assertSee(route('sessions.tv', $session), false)->assertDontSee('data-session-app', false);
        $this->get(route('sessions.tv', $session))->assertOk()->assertSee(route('sessions.state', $session), false)->assertDontSee('data-session-command')->assertDontSee('workspace-nav');
        $this->get(route('sessions.control', $session))->assertOk()->assertSee(route('sessions.state', $session), false)->assertSee('Controles principais')->assertSee('Mais controles da aula')->assertSee('AULA #'.$session->id)->assertDontSee('workspace-nav')->assertDontSee('Configurações da Box');
        $this->assertDatabaseCount('workout_sessions', 1);
        $this->assertDatabaseCount('timer_sessions', 3);
        $this->assertDatabaseCount('session_commands', 0);
        $this->assertDatabaseHas('workout_sessions', ['id' => $session->id, 'started_at' => null, 'controller_client_id' => null, 'version' => 1]);
    }

    public function test_prepared_and_running_states_offer_the_correct_action_and_restore_the_exact_session(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 12:00:00', 'UTC'));
        $user = User::factory()->create();
        [$workout, $state] = $this->sessionFor($user, [...$this->workoutInput(), 'scheduled_for' => now($user->box->timezone)->toDateString()]);
        $this->get(route('coach.today'))->assertOk()->assertSee('Continuar preparação')->assertSee('Aula #'.$state['id']);
        $this->post(route('coach.prepare', $workout))->assertRedirect(route('coach.ready', $state['id']));
        $state = $this->command($state, 'start')->assertOk()->json('data');
        $this->travel(2)->seconds();
        $this->get(route('coach.today'))->assertOk()->assertSee('Retomar aula')->assertSee(route('sessions.control', $state['id']), false);
        $this->post(route('coach.prepare', $workout), ['preparation_seconds' => '30', 'auto_advance' => '1'])
            ->assertRedirect(route('coach.ready', $state['id']));
        $this->get(route('coach.ready', $state['id']))->assertOk()->assertSee('Sua aula está preservada.');
        $state = $this->command($state, 'pause')->assertOk()->json('data');
        $this->getJson(route('sessions.state', $state['id']))->assertOk()
            ->assertJsonPath('data.auto_advance', false)->assertJsonPath('data.timers.0.config.preparation_ms', 10000)
            ->assertJsonPath('data.timers.0.state', 'paused')->assertJsonPath('data.timers.0.elapsed_ms', 2000);
        $this->assertDatabaseCount('workout_sessions', 1);
    }

    public function test_finished_history_requires_confirmation_and_rejects_stale_new_class_requests(): void
    {
        $user = User::factory()->create();
        [$workout, $old] = $this->sessionFor($user, [...$this->workoutInput(), 'scheduled_for' => now($user->box->timezone)->toDateString()]);
        $this->command($this->completeLastStage($old), 'finish')->assertOk();
        $this->get(route('coach.today'))->assertOk()->assertSee('Iniciar nova sessão')->assertSee('new_session_confirmation');
        $this->post(route('coach.prepare', $workout))->assertSessionHasErrors('new_session_confirmation');
        $this->post(route('sessions.store', $workout))->assertSessionHasErrors('new_session_confirmation');
        $this->post(route('coach.prepare', $workout), ['new_session_confirmation' => 1, 'previous_session_id' => 99999])->assertSessionHasErrors('new_session_confirmation');
        $payload = ['new_session_confirmation' => 1, 'previous_session_id' => $old['id']];
        $response = $this->post(route('coach.prepare', $workout), $payload)->assertSessionHasNoErrors();
        $new = $this->get(route('sessions.index'))->viewData('sessions')->first();
        $response->assertRedirect(route('coach.ready', $new));
        $this->assertNotSame($old['id'], $new->id);
        $this->post(route('coach.prepare', $workout), $payload)->assertRedirect(route('coach.ready', $new));
        $this->assertDatabaseCount('workout_sessions', 2);
        $this->get(route('sessions.summary', $old['id']))->assertOk()->assertSee('Aula concluída')->assertSee('Voltar ao Modo Aula')->assertSee('Iniciar outra aula');
        $state = $this->postJson(route('sessions.lease', $new), ['client_id' => (string) Str::uuid()])->assertOk()->json('data');
        $this->command($this->completeLastStage($state), 'finish')->assertOk();
        $this->post(route('coach.prepare', $workout), $payload)->assertSessionHasErrors('new_session_confirmation');
        $this->getJson(route('sessions.state', $old['id']))->assertJsonPath('data.status', 'completed');
        $this->assertDatabaseCount('workout_sessions', 2);
    }

    public function test_pending_revision_is_refreshed_but_running_snapshot_stays_immutable(): void
    {
        $user = User::factory()->create();
        [$workout, $old] = $this->sessionFor($user, [...$this->workoutInput(), 'title' => 'Original']);
        $updated = [...$this->workoutInput(), 'title' => 'Atualizada'];
        $updated['blocks'][0]['suggested_loads'] = ['rx' => '60 kg'];
        $this->put(route('workouts.update', $workout), $updated)->assertSessionHasNoErrors();
        $this->post(route('coach.prepare', $workout))->assertSessionHasNoErrors();
        $session = $this->get(route('sessions.index'))->viewData('sessions')->first();
        $this->get(route('coach.ready', $session))->assertOk()->assertSee('Atualizada')->assertSee('60 kg');
        $this->assertNotSame($old['id'], $session->id);
        $state = $this->postJson(route('sessions.lease', $session), ['client_id' => (string) Str::uuid()])->json('data');
        $state = $this->command($state, 'start')->assertOk()->json('data');
        $this->put(route('workouts.update', $workout), [...$updated, 'title' => 'Nova revisão'])->assertSessionHasNoErrors();
        $this->post(route('coach.prepare', $workout))->assertRedirect(route('coach.ready', $session));
        $this->get(route('coach.ready', $session))->assertOk()->assertSee('O treino cadastrado foi alterado')->assertSee('Atualizada')->assertDontSee('Nova revisão');
        $this->getJson(route('sessions.state', $session))->assertOk()->assertJsonPath('data.title', 'Atualizada')->assertJsonPath('data.timers.0.suggested_loads.rx', '60 kg');
    }

    public function test_disabled_or_draft_workouts_cannot_create_classes_but_started_disabled_class_can_finish(): void
    {
        $user = User::factory()->create();
        [$workout, $state] = $this->sessionFor($user, [...$this->workoutInput('hyrox'), 'scheduled_for' => now($user->box->timezone)->toDateString()]);
        $state = $this->command($state, 'start')->assertOk()->json('data');
        $user->box->modalities()->sync(TrainingModality::where('slug', 'crossfit')->pluck('id')->all());
        $response = $this->get(route('coach.today'))->assertOk()->assertSee('Aulas em andamento')->assertSee('Modalidade desativada; esta aula pode ser concluída.')->assertSee(route('sessions.control', $state['id']), false);
        $this->assertCount(0, $response->viewData('workouts'));
        $this->command($this->completeLastStage($state), 'finish')->assertOk();
        $this->post(route('coach.prepare', $workout), ['new_session_confirmation' => 1, 'previous_session_id' => $state['id']])->assertSessionHasErrors('training_modality_id');
        $draft = $this->createWorkoutFor($user, $this->workoutInput('crossfit', 'main', 'draft'));
        $this->post(route('coach.prepare', $draft))->assertSessionHasErrors('workout');
        $this->assertDatabaseCount('workout_sessions', 1);
    }

    public function test_new_routes_cannot_prepare_or_inspect_another_box_session(): void
    {
        [$workout, $state] = $this->sessionFor(User::factory()->create());
        $this->actingAs(User::factory()->create())->post(route('coach.prepare', $workout))->assertNotFound();
        $this->get(route('coach.ready', $state['id']))->assertNotFound();
        $this->get(route('coach.today'))->assertOk()->assertDontSee($workout->title);
        $this->assertDatabaseCount('workout_sessions', 1);
    }

    public function test_workout_class_history_identifies_each_run_and_keeps_active_choice_explicit(): void
    {
        $user = User::factory()->create();
        [$workout, $old] = $this->sessionFor($user, [...$this->workoutInput(), 'scheduled_for' => now($user->box->timezone)->toDateString()]);
        $this->command($this->completeLastStage($old), 'finish')->assertOk();
        $this->post(route('coach.prepare', $workout), ['new_session_confirmation' => 1, 'previous_session_id' => $old['id']])->assertSessionHasNoErrors();
        $new = $this->get(route('sessions.index'))->viewData('sessions')->first();
        $response = $this->get(route('coach.history', $workout))->assertOk()->assertSee('Aulas deste treino')->assertSee('Aula #'.$old['id'])->assertSee('Aula #'.$new->id)->assertSee(route('sessions.control', $new), false)->assertSee(route('sessions.summary', $old['id']), false)->assertDontSee('workspace-nav');
        $this->assertSame([$new->id, $old['id']], $response->viewData('sessions')->modelKeys());
        $this->get(route('coach.today'))->assertOk()->assertSee('Continuar preparação')->assertSee('Aula #'.$new->id);
        $this->actingAs(User::factory()->create())->get(route('coach.history', $workout))->assertNotFound();
    }

    public function test_today_and_ready_normalize_automatic_completion_with_the_existing_state_service(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 12:00:00', 'UTC'));
        $user = User::factory()->create();
        $input = $this->workoutInput();
        $input['blocks'][0]['duration_seconds'] = 2;
        [$workout, $state] = $this->sessionFor($user, $input, ['preparation_seconds' => 0, 'auto_advance' => true]);
        $this->command($state, 'start')->assertOk();
        $this->travel(3)->seconds();
        $this->get(route('coach.today'))->assertOk()->assertSee('Última etapa concluída')->assertSee('Retomar aula');
        $finishedBlock = $this->getJson(route('sessions.state', $state['id']))->assertOk()->assertJsonPath('data.timers.0.state', 'completed')->json('data');
        $this->command($finishedBlock, 'finish')->assertOk();
        $this->get(route('coach.today'))->assertOk()->assertSee('Iniciar nova sessão')->assertDontSee('Retomar aula');
        $this->get(route('coach.ready', $state['id']))->assertOk()->assertSee('Esta aula já foi encerrada.');
        $this->assertDatabaseHas('workout_sessions', ['id' => $state['id'], 'status' => 'completed', 'active_workout_id' => null]);
        $this->post(route('coach.prepare', $workout))->assertSessionHasErrors('new_session_confirmation');
    }

    public function test_active_session_unique_constraint_is_a_second_concurrency_guard(): void
    {
        $user = User::factory()->create();
        [, $state] = $this->sessionFor($user);
        app(CurrentBox::class)->set($user->box_id);
        try {
            $copy = WorkoutSession::findOrFail($state['id'])->replicate();
            $this->expectException(UniqueConstraintViolationException::class);
            $copy->save();
        } finally {
            app(CurrentBox::class)->clear();
        }
    }
}
