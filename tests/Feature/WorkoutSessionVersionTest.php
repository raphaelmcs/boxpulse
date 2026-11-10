<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkoutSession;
use App\Tenancy\CurrentBox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithSessions;
use Tests\TestCase;

class WorkoutSessionVersionTest extends TestCase
{
    use InteractsWithSessions, RefreshDatabase;

    private function updatedInput(): array
    {
        $data = $this->workoutInput('crossfit', 'full');
        $data['title'] = 'Clean & Jerk';
        $data['blocks'][1]['title'] = 'Clean & Jerk technique';
        $data['blocks'][1]['description'] = '5 x 3 clean';
        $data['blocks'][1]['duration_seconds'] = 600;
        $data['blocks'][2]['description'] = '12 clean and jerks';
        $data['blocks'][2]['timer_type'] = 'amrap';
        $data['blocks'][2]['duration_seconds'] = 720;

        return $data;
    }

    public function test_front_squat_to_clean_and_jerk_replaces_pending_snapshot_with_all_updated_content(): void
    {
        $user = User::factory()->create();
        $original = [...$this->workoutInput('crossfit', 'full'), 'title' => 'Front Squat'];
        [$workout, $old] = $this->sessionFor($user, $original);
        $this->get(route('workouts.preview', $workout))->assertOk()->assertSee('Front Squat');
        $this->put(route('workouts.update', $workout), $this->updatedInput())->assertSessionHasNoErrors();
        foreach (['workouts.show', 'workouts.preview'] as $route) {
            $this->get(route($route, $workout))->assertOk()->assertSee('Clean &amp; Jerk', false);
        }
        $response = $this->post(route('sessions.store', $workout))->assertSessionHasNoErrors()->assertRedirect();
        preg_match('~/sessions/(\d+)/control$~', $response->headers->get('Location'), $matches);
        $newId = (int) $matches[1];
        $this->assertNotSame($old['id'], $newId);
        $this->getJson(route('sessions.state', $newId))->assertOk()->assertJsonPath('data.title', 'Clean & Jerk')->assertJsonPath('data.source.session_version', 2)->assertJsonPath('data.source.updated', false)->assertJsonPath('data.timers.1.title', 'Clean & Jerk technique')->assertJsonPath('data.timers.1.config.duration_ms', 600000)->assertJsonPath('data.timers.2.description', '12 clean and jerks')->assertJsonPath('data.timers.2.config.duration_ms', 720000);
        foreach (['sessions.control', 'sessions.tv'] as $route) {
            $this->get(route($route, $newId))->assertOk()->assertSee('Clean &amp; Jerk', false)->assertDontSee('Front Squat');
        }
        $this->assertDatabaseHas('workout_sessions', ['id' => $old['id'], 'title' => 'Front Squat', 'status' => 'cancelled', 'superseded_by_id' => $newId]);
        $this->post(route('sessions.store', $workout))->assertRedirect(route('sessions.control', $newId));
        $this->assertDatabaseCount('workout_sessions', 2);
    }

    public function test_running_session_is_preserved_and_explicit_restart_creates_current_published_version(): void
    {
        $user = User::factory()->create();
        [$workout, $old] = $this->sessionFor($user, [...$this->workoutInput('crossfit', 'full'), 'title' => 'Front Squat']);
        $old = $this->command($old, 'start')->assertOk()->json('data');
        $this->put(route('workouts.update', $workout), $this->updatedInput())->assertSessionHasNoErrors();
        $this->post(route('sessions.store', $workout))->assertRedirect(route('sessions.control', $old['id']));
        $this->getJson(route('sessions.state', $old['id']))->assertOk()->assertJsonPath('data.title', 'Front Squat')->assertJsonPath('data.source.updated', true)->assertJsonPath('data.source.title', 'Clean & Jerk')->assertJsonPath('data.timers.2.config.duration_ms', 900000);
        $payload = ['client_id' => $old['controller_client_id'], 'expected_version' => $old['version'], 'expected_workout_version' => 2, 'confirmation' => 1];
        $this->post(route('sessions.restart', $old['id']), $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('workout_sessions', ['id' => $old['id'], 'title' => 'Front Squat', 'status' => 'cancelled']);
        $this->assertDatabaseHas('workout_sessions', ['title' => 'Clean & Jerk', 'workout_version' => 2, 'status' => 'active']);
        $this->command($old, 'pause')->assertConflict();
        $this->post(route('sessions.restart', $old['id']), $payload)->assertSessionHasErrors('session');
        $this->assertDatabaseCount('workout_sessions', 2);
    }

    public function test_finished_sessions_remain_unchanged_and_are_never_reused(): void
    {
        [$workout, $old] = $this->sessionFor(User::factory()->create(), [...$this->workoutInput('crossfit', 'full'), 'title' => 'Front Squat']);
        $this->command($old, 'finish')->assertOk();
        $this->put(route('workouts.update', $workout), $this->updatedInput())->assertSessionHasNoErrors();
        $this->post(route('sessions.store', $workout), ['new_session_confirmation' => 1, 'previous_session_id' => $old['id']])->assertSessionHasNoErrors();
        $this->getJson(route('sessions.state', $old['id']))->assertOk()->assertJsonPath('data.title', 'Front Squat')->assertJsonPath('data.status', 'completed')->assertJsonPath('data.timers.2.description', $old['timers'][2]['description']);
        $this->assertDatabaseHas('workout_sessions', ['title' => 'Clean & Jerk', 'status' => 'active']);
        $this->assertDatabaseCount('workout_sessions', 2);
    }

    public function test_restart_requires_confirmation_current_versions_lease_and_control_permission(): void
    {
        $owner = User::factory()->create(['role' => 'coach']);
        [$workout, $state] = $this->sessionFor($owner, $this->workoutInput('crossfit', 'full'));
        $state = $this->command($state, 'start')->assertOk()->json('data');
        $this->put(route('workouts.update', $workout), $this->updatedInput())->assertSessionHasNoErrors();
        $payload = ['client_id' => $state['controller_client_id'], 'expected_version' => $state['version'], 'expected_workout_version' => 2];
        $this->post(route('sessions.restart', $state['id']), $payload)->assertSessionHasErrors('confirmation');
        $this->post(route('sessions.restart', $state['id']), [...$payload, 'confirmation' => 1, 'expected_workout_version' => 1])->assertSessionHasErrors('session');
        $this->post(route('sessions.restart', $state['id']), [...$payload, 'confirmation' => 1, 'expected_version' => 999])->assertSessionHasErrors('session');
        $this->actingAs(User::factory()->create(['box_id' => $owner->box_id, 'role' => 'coach']))->post(route('sessions.restart', $state['id']), [...$payload, 'confirmation' => 1])->assertForbidden();
        $this->actingAs(User::factory()->create())->post(route('sessions.restart', $state['id']), [...$payload, 'confirmation' => 1])->assertNotFound();
        $this->assertDatabaseCount('workout_sessions', 1);
    }

    public function test_draft_changes_require_publication_before_new_snapshot_and_legacy_pending_is_replaced(): void
    {
        $user = User::factory()->create();
        [$workout, $old] = $this->sessionFor($user, $this->workoutInput('crossfit', 'full'));
        $this->put(route('workouts.update', $workout), [...$this->updatedInput(), 'status' => 'draft'])->assertSessionHasNoErrors();
        $this->post(route('sessions.store', $workout))->assertSessionHasErrors('workout');
        $this->assertDatabaseCount('workout_sessions', 1);
        $this->post(route('workouts.publish', $workout))->assertSessionHasNoErrors();
        app(CurrentBox::class)->set($user->box_id);
        $legacy = WorkoutSession::findOrFail($old['id']);
        $legacy->workout_version = null;
        $legacy->save();
        app(CurrentBox::class)->clear();
        $this->post(route('sessions.store', $workout))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('workout_sessions', ['title' => 'Clean & Jerk', 'workout_version' => 2, 'status' => 'active']);
        $this->assertDatabaseHas('workout_sessions', ['id' => $old['id'], 'workout_version' => null, 'status' => 'cancelled']);
    }

    public function test_stale_pending_session_cannot_be_started_without_preparing_the_published_update(): void
    {
        [$workout, $state] = $this->sessionFor(User::factory()->create(), $this->workoutInput('crossfit', 'full'));
        $this->put(route('workouts.update', $workout), $this->updatedInput())->assertSessionHasNoErrors();
        $this->command($state, 'start')->assertConflict()->assertJsonPath('data.execution_started', false)->assertJsonPath('data.source.updated', true);
        $this->command($state, 'next')->assertConflict();
        $this->command($state, 'complete_block')->assertConflict();
        $this->assertDatabaseHas('workout_sessions', ['id' => $state['id'], 'started_at' => null, 'status' => 'active']);
        $this->post(route('sessions.store', $workout))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('workout_sessions', ['title' => 'Clean & Jerk', 'workout_version' => 2, 'status' => 'active']);
    }
}
