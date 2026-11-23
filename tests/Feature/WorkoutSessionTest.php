<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithSessions;
use Tests\TestCase;

class WorkoutSessionTest extends TestCase
{
    use InteractsWithSessions, RefreshDatabase;

    public function test_prepare_is_idempotent_and_snapshots_survive_workout_edits(): void
    {
        $user = User::factory()->create();
        [$workout, $state] = $this->sessionFor($user, $this->workoutInput('crossfit', 'full'));
        $this->post(route('sessions.store', $workout))->assertRedirect(route('sessions.control', $state['id']));
        $this->assertDatabaseCount('workout_sessions', 1);
        $this->assertDatabaseCount('timer_sessions', 3);
        $changed = $this->workoutInput('crossfit', 'full');
        $changed['blocks'][2]['description'] = 'Updated source only';
        $this->put(route('workouts.update', $workout), $changed)->assertSessionHasNoErrors();
        $this->getJson(route('sessions.state', $state['id']))->assertOk()->assertJsonPath('data.timers.2.description', $state['timers'][2]['description']);
        $this->delete(route('workouts.destroy', $workout), ['confirmation' => 1])->assertSessionHasErrors('workout');
        $this->assertDatabaseCount('workouts', 1);
    }

    public function test_drafts_cannot_prepare_a_session(): void
    {
        $user = User::factory()->create();
        $workout = $this->createWorkoutFor($user, $this->workoutInput(status: 'draft'));
        $this->actingAs($user)->post(route('sessions.store', $workout))->assertSessionHasErrors('workout');
        $this->assertDatabaseCount('workout_sessions', 0);
    }

    public function test_preparation_pause_resume_and_reload_preserve_the_server_clock(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 12:00:00', 'UTC'));
        [, $state] = $this->sessionFor(User::factory()->create());
        $state = $this->command($state, 'start')->assertOk()->assertJsonPath('data.timers.0.state', 'preparing')->json('data');
        $this->travel(3)->seconds();
        $state = $this->command($state, 'pause')->assertOk()->assertJsonPath('data.timers.0.elapsed_ms', 3000)->assertJsonPath('data.timers.0.state', 'paused')->json('data');
        $this->travel(60)->seconds();
        $state = $this->getJson(route('sessions.state', $state['id']))->assertOk()->assertJsonPath('data.timers.0.elapsed_ms', 3000)->json('data');
        $state = $this->postJson(route('sessions.lease', $state['id']), ['client_id' => $state['controller_client_id']])->assertOk()->json('data');
        $state = $this->command($state, 'resume')->assertOk()->assertJsonPath('data.timers.0.state', 'preparing')->json('data');
        $this->travel(8)->seconds();
        $state = $this->getJson(route('sessions.state', $state['id']))->assertOk()->assertJsonPath('data.timers.0.state', 'running')->json('data');
        $this->assertSame(3000, $state['timers'][0]['elapsed_ms']);
        $this->assertSame(8000, $state['server_time_ms'] - $state['timers'][0]['effective_started_at_ms']);
    }

    public function test_countdown_automatically_completes_without_advancing_another_block(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 12:00:00', 'UTC'));
        $input = $this->workoutInput('crossfit', 'full');
        $input['blocks'][0]['timer_type'] = 'countdown';
        $input['blocks'][0]['duration_seconds'] = 5;
        [, $state] = $this->sessionFor(User::factory()->create(), $input, ['preparation_seconds' => 3]);
        $this->command($state, 'start')->assertOk();
        $this->travel(9)->seconds();
        $this->getJson(route('sessions.state', $state['id']))->assertOk()->assertJsonPath('data.current_position', 0)->assertJsonPath('data.status', 'active')->assertJsonPath('data.timers.0.state', 'completed')->assertJsonPath('data.timers.0.elapsed_ms', 8000)->assertJsonPath('data.timers.1.state', 'idle');
    }

    public function test_for_time_stops_at_cap_and_optional_continue_is_preserved(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 12:00:00', 'UTC'));
        $user = User::factory()->create();
        $input = $this->workoutInput();
        $input['blocks'][0]['duration_seconds'] = 2;
        [, $first] = $this->sessionFor($user, $input, ['preparation_seconds' => 0]);
        $this->command($first, 'start')->assertOk();
        $this->travel(3)->seconds();
        $this->getJson(route('sessions.state', $first['id']))->assertOk()->assertJsonPath('data.timers.0.state', 'completed')->assertJsonPath('data.timers.0.elapsed_ms', 2000);
        $input['blocks'][0]['timer_settings']['stop_at_cap'] = false;
        [, $second] = $this->sessionFor($user, $input, ['preparation_seconds' => 0]);
        $this->command($second, 'start')->assertOk();
        $this->travel(3)->seconds();
        $this->getJson(route('sessions.state', $second['id']))->assertOk()->assertJsonPath('data.timers.0.state', 'running')->assertJsonPath('data.timers.0.config.limit_ms', null);
    }

    public function test_restart_requires_confirmation_and_returns_to_idle_without_starting(): void
    {
        [, $state] = $this->sessionFor(User::factory()->create());
        $state = $this->command($state, 'start')->assertOk()->json('data');
        $this->command($state, 'reset')->assertUnprocessable()->assertJsonValidationErrors('confirmation');
        $this->command($state, 'reset', ['confirmation' => true])->assertOk()->assertJsonPath('data.timers.0.state', 'idle')->assertJsonPath('data.timers.0.elapsed_ms', 0)->assertJsonPath('data.timers.0.effective_started_at_ms', null);
    }

    public function test_manual_next_previous_and_block_completion_never_implicitly_start_next_timer(): void
    {
        [, $state] = $this->sessionFor(User::factory()->create(), $this->workoutInput('crossfit', 'full'));
        $state = $this->command($state, 'start')->assertOk()->json('data');
        $state = $this->command($state, 'next')->assertOk()->assertJsonPath('data.current_position', 1)->assertJsonPath('data.timers.0.state', 'completed')->assertJsonPath('data.timers.1.state', 'idle')->json('data');
        $state = $this->command($state, 'previous')->assertOk()->assertJsonPath('data.current_position', 0)->json('data');
        $this->command($state, 'previous')->assertUnprocessable();
        $state = $this->command($state, 'next')->assertOk()->json('data');
        $this->command($state, 'complete_block')->assertOk()->assertJsonPath('data.timers.1.state', 'completed');
    }

    public function test_finish_and_cancel_preserve_history_and_allow_a_new_session(): void
    {
        [$workout, $state] = $this->sessionFor(User::factory()->create());
        $this->command($this->completeLastStage($state), 'finish')->assertOk()->assertJsonPath('data.status', 'completed');
        $response = $this->post(route('sessions.store', $workout), ['new_session_confirmation' => 1, 'previous_session_id' => $state['id']])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNotSame(route('sessions.control', $state['id']), $response->headers->get('Location'));
        $this->assertDatabaseCount('workout_sessions', 2);
    }

    public function test_cancel_is_terminal(): void
    {
        [, $state] = $this->sessionFor(User::factory()->create());
        $state = $this->command($state, 'cancel')->assertOk()->assertJsonPath('data.status', 'cancelled')->json('data');
        $this->command($state, 'start')->assertConflict();
    }

    public function test_finish_requires_the_final_stage_and_is_idempotent_with_the_original_timestamp(): void
    {
        $begin = Carbon::parse('2026-11-23 15:00:00', 'UTC');
        $this->travelTo($begin);
        $admin = User::factory()->create();
        $input = $this->workoutInput('crossfit', 'full');
        $input['blocks'][2]['timer_type'] = 'none';
        unset($input['blocks'][2]['duration_seconds']);
        [$workout, $state] = $this->sessionFor($admin, $input);
        $this->command($state, 'finish')->assertUnprocessable();
        $state = $this->command($state, 'next')->assertOk()->json('data');
        $state = $this->command($state, 'next')->assertOk()->json('data');
        $this->command($state, 'next')->assertUnprocessable();
        $this->command($state, 'finish')->assertUnprocessable();
        $state = $this->command($state, 'complete_block')->assertOk()->assertJsonPath('data.status', 'active')->json('data');
        $this->actingAs(User::factory()->create(['box_id' => $admin->box_id, 'role' => 'coach']))
            ->command($state, 'finish')->assertForbidden();
        $this->actingAs(User::factory()->create())->command($state, 'finish')->assertNotFound();
        $this->actingAs($admin);
        $this->travel(2)->seconds();
        $command = ['command_id' => (string) Str::uuid()];
        $response = $this->command($state, 'finish', $command)->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertSame($begin->copy()->addSeconds(2)->format('Y-m-d\TH:i:s.u\Z'), $response->json('data.finished_at'));
        $this->assertSame($state['timers'], $response->json('data.timers'));
        $this->travel(1)->seconds();
        $this->command($state, 'finish', $command)->assertOk()->assertJsonPath('data.finished_at', $response->json('data.finished_at'));
        $this->command($state, 'finish')->assertConflict();
        $this->assertDatabaseCount('session_commands', 4);
        $this->get(route('sessions.control', $state['id']))->assertOk()->assertSee('Iniciar nova aula');
        $this->get(route('sessions.tv', $state['id']))->assertOk()->assertSee('data-session-status="completed"', false);
        $this->post(route('coach.prepare', $workout))->assertSessionHasErrors('new_session_confirmation');
        $this->post(route('coach.prepare', $workout), ['new_session_confirmation' => 1, 'previous_session_id' => $state['id']])->assertSessionHasNoErrors();
        $newClass = $this->get(route('sessions.index'))->viewData('sessions')->first();
        $this->assertNotSame($state['id'], $newClass->id);
        $this->getJson(route('sessions.state', $newClass))->assertOk()->assertJsonPath('data.timers.0.state', 'idle');
        $this->getJson(route('sessions.state', $state['id']))->assertOk()->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.finished_at', $response->json('data.finished_at'));
        $this->assertDatabaseCount('workout_sessions', 2);
        $this->assertDatabaseCount('timer_sessions', 6);
    }

    public function test_auto_advance_is_explicit_and_catches_up_after_background_suspension(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 12:00:00', 'UTC'));
        $input = $this->workoutInput('crossfit', 'full');
        foreach ($input['blocks'] as &$block) {
            $block['timer_type'] = $block['type'] === 'workout' ? 'amrap' : 'countdown';
            $block['duration_seconds'] = 2;
        }
        [, $state] = $this->sessionFor(User::factory()->create(), $input, ['preparation_seconds' => 0, 'auto_advance' => true]);
        $this->command($state, 'start')->assertOk();
        $this->travel(10)->seconds();
        $this->getJson(route('sessions.state', $state['id']))->assertOk()->assertJsonPath('data.status', 'active')->assertJsonPath('data.current_position', 2)->assertJsonPath('data.timers.2.state', 'completed')->assertJsonPath('data.timers.0.elapsed_ms', 2000)->assertJsonPath('data.timers.1.elapsed_ms', 2000)->assertJsonPath('data.timers.2.elapsed_ms', 2000);
    }

    public function test_previous_step_is_not_automatically_advanced_again_after_completion(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 12:00:00', 'UTC'));
        $input = $this->workoutInput('crossfit', 'full');
        $input['blocks'][0]['timer_type'] = 'countdown';
        $input['blocks'][0]['duration_seconds'] = 2;
        [, $state] = $this->sessionFor(User::factory()->create(), $input, ['preparation_seconds' => 0, 'auto_advance' => true]);
        $this->command($state, 'start')->assertOk();
        $this->travel(3)->seconds();
        $state = $this->getJson(route('sessions.state', $state['id']))->assertOk()->assertJsonPath('data.current_position', 1)->json('data');
        $state = $this->command($state, 'previous')->assertOk()->assertJsonPath('data.current_position', 0)->json('data');
        $this->getJson(route('sessions.state', $state['id']))->assertOk()->assertJsonPath('data.current_position', 0)->assertJsonPath('data.status', 'active');
    }

    public function test_auto_advance_waits_during_pause_and_respects_the_next_optional_stage_preparation(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 12:00:00', 'UTC'));
        $input = $this->workoutInput('crossfit', 'full');
        $input['blocks'][0]['timer_type'] = 'countdown';
        $input['blocks'][0]['duration_seconds'] = 2;
        $input['blocks'][0]['timer_settings']['preparation_seconds'] = 0;
        $input['blocks'][2]['timer_settings']['preparation_seconds'] = 20;
        $input['blocks'] = [$input['blocks'][0], $input['blocks'][2]];
        [, $state] = $this->sessionFor(User::factory()->create(), $input, ['auto_advance' => '1']);

        $state = $this->command($state, 'start')->assertOk()->json('data');
        $this->travel(1)->seconds();
        $state = $this->command($state, 'pause')->assertOk()->json('data');
        $this->travel(5)->seconds();
        $this->getJson(route('sessions.state', $state['id']))->assertOk()
            ->assertJsonPath('data.current_position', 0)->assertJsonPath('data.timers.0.state', 'paused');
        $state = $this->postJson(route('sessions.lease', $state['id']), ['client_id' => $state['controller_client_id']])->assertOk()->json('data');
        $this->command($state, 'resume')->assertOk();
        $this->travel(2)->seconds();
        $next = $this->getJson(route('sessions.state', $state['id']))->assertOk()
            ->assertJsonPath('data.current_position', 1)->assertJsonPath('data.timers.0.state', 'completed')
            ->assertJsonPath('data.timers.1.block_type', 'workout')->assertJsonPath('data.timers.1.state', 'preparing')
            ->assertJsonPath('data.timers.1.config.preparation_ms', 20000)->json('data');
        $this->getJson(route('sessions.state', $state['id']))->assertOk()->assertJsonPath('data.version', $next['version']);
        $this->travel(19)->seconds();
        $this->getJson(route('sessions.state', $state['id']))->assertOk()->assertJsonPath('data.timers.1.state', 'running');
    }

    public function test_auto_advance_stops_at_an_untimed_stage_until_the_coach_continues(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 12:00:00', 'UTC'));
        $input = $this->workoutInput('crossfit', 'full');
        $input['blocks'][0]['timer_type'] = 'countdown';
        $input['blocks'][0]['duration_seconds'] = 2;
        $input['blocks'][1]['timer_type'] = 'none';
        unset($input['blocks'][1]['duration_seconds']);
        [, $state] = $this->sessionFor(User::factory()->create(), $input, ['preparation_seconds' => '0', 'auto_advance' => '1']);
        $this->command($state, 'start')->assertOk();

        $this->travel(60)->seconds();
        $this->getJson(route('sessions.state', $state['id']))->assertOk()
            ->assertJsonPath('data.current_position', 1)->assertJsonPath('data.timers.0.state', 'completed')
            ->assertJsonPath('data.timers.1.config.limit_ms', null)->assertJsonPath('data.timers.2.state', 'idle');
        $state = $this->postJson(route('sessions.lease', $state['id']), ['client_id' => $state['controller_client_id']])->assertOk()->json('data');
        $this->command($state, 'next')->assertOk()
            ->assertJsonPath('data.current_position', 2)->assertJsonPath('data.timers.2.state', 'idle');
    }

    public function test_elapsed_time_preserves_millisecond_precision(): void
    {
        $start = Carbon::parse('2026-11-10 12:00:00.125', 'UTC');
        $this->travelTo($start);
        [, $state] = $this->sessionFor(User::factory()->create(), options: ['preparation_seconds' => 0]);
        $state = $this->command($state, 'start')->assertOk()->json('data');
        $this->travelTo($start->copy()->addMilliseconds(1375));
        $this->command($state, 'pause')->assertOk()->assertJsonPath('data.timers.0.elapsed_ms', 1375);
    }

    public function test_control_tv_history_and_summary_are_accessible_with_theme_and_safe_content(): void
    {
        $user = User::factory()->create();
        $input = $this->workoutInput();
        $input['blocks'][0]['description'] = '<script>alert(1)</script>';
        [, $state] = $this->sessionFor($user, $input);
        $this->get(route('sessions.control', $state['id']))->assertOk()->assertSee('Iniciar etapa')->assertSee('Abrir na TV');
        $this->get(route('sessions.tv', $state['id']))->assertOk()->assertSee('data-view="tv"', false)->assertDontSee('data-session-command=', false)->assertSee('--box-primary:', false);
        $this->get(route('sessions.summary', $state['id']))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get(route('sessions.index'))->assertOk()->assertSee($input['title']);
    }
}
