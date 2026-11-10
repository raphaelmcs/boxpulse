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
        $this->post(route('coach.prepare', $workout))->assertRedirect(route('coach.ready', $state['id']));
        $this->get(route('coach.ready', $state['id']))->assertOk()->assertSee('Sua aula está preservada.');
        $state = $this->command($state, 'pause')->assertOk()->json('data');
        $this->getJson(route('sessions.state', $state['id']))->assertOk()->assertJsonPath('data.timers.0.state', 'paused')->assertJsonPath('data.timers.0.elapsed_ms', 2000);
        $this->assertDatabaseCount('workout_sessions', 1);
    }

    public function test_finished_history_requires_confirmation_and_rejects_stale_new_class_requests(): void
    {
        $user = User::factory()->create();
        [$workout, $old] = $this->sessionFor($user, [...$this->workoutInput(), 'scheduled_for' => now($user->box->timezone)->toDateString()]);
        $this->command($old, 'finish')->assertOk();
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
        $this->command($state, 'finish')->assertOk();
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
        $this->command($state, 'finish')->assertOk();
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
        $this->command($old, 'finish')->assertOk();
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
