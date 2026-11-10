<?php

namespace Tests\Feature;

use App\Models\TimerSession;
use App\Models\User;
use App\Tenancy\CurrentBox;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithSessions;
use Tests\TestCase;

class SessionSynchronizationTest extends TestCase
{
    use InteractsWithSessions, RefreshDatabase;

    public function test_duplicate_command_ids_do_not_advance_two_steps(): void
    {
        [, $state] = $this->sessionFor(User::factory()->create(), $this->workoutInput('crossfit', 'full'));
        $id = (string) Str::uuid();
        $response = $this->command($state, 'next', ['command_id' => $id])->assertOk()->assertJsonPath('data.current_position', 1);
        $this->command($state, 'next', ['command_id' => $id])->assertOk()->assertJsonPath('data.current_position', 1)->assertJsonPath('data.version', $response->json('data.version'));
        $this->assertDatabaseCount('session_commands', 1);
        $this->command($state, 'previous', ['command_id' => $id])->assertConflict();
    }

    public function test_stale_version_and_second_controller_cannot_overwrite_state(): void
    {
        [, $state] = $this->sessionFor(User::factory()->create(), $this->workoutInput('crossfit', 'full'));
        $this->command($state, 'next')->assertOk();
        $this->command($state, 'next')->assertConflict()->assertJsonPath('data.current_position', 1);
        $other = (string) Str::uuid();
        $this->postJson(route('sessions.lease', $state['id']), ['client_id' => $other])->assertConflict();
        $new = $this->postJson(route('sessions.lease', $state['id']), ['client_id' => $other, 'takeover' => true])->assertOk()->json('data');
        $this->command($state, 'next')->assertConflict();
        $this->command($new, 'next')->assertOk()->assertJsonPath('data.current_position', 2);
    }

    public function test_expired_lease_blocks_commands_until_controller_reacquires(): void
    {
        [, $state] = $this->sessionFor(User::factory()->create());
        $this->travel(config('boxpulse.controller_lease_seconds') + 1)->seconds();
        $this->command($state, 'start')->assertConflict();
        $state = $this->postJson(route('sessions.lease', $state['id']), ['client_id' => $state['controller_client_id']])->assertOk()->json('data');
        $this->command($state, 'start')->assertOk();
    }

    public function test_session_state_is_shared_and_readers_cannot_send_control_commands(): void
    {
        $owner = User::factory()->create(['role' => 'coach']);
        [, $state] = $this->sessionFor($owner);
        $this->command($state, 'start')->assertOk();
        $reader = User::factory()->create(['role' => 'coach', 'box_id' => $owner->box_id]);
        $this->actingAs($reader)->getJson(route('sessions.state', $state['id']))->assertOk()->assertJsonPath('data.can_control', false)->assertJsonPath('data.timers.0.state', 'preparing');
        $this->command($state, 'pause')->assertForbidden();
        $this->postJson(route('sessions.lease', $state['id']), ['client_id' => (string) Str::uuid(), 'takeover' => true])->assertForbidden();
    }

    public function test_foreign_box_cannot_read_control_or_claim_a_session(): void
    {
        [, $state] = $this->sessionFor(User::factory()->create());
        $this->actingAs(User::factory()->create());
        foreach (['sessions.state', 'sessions.control', 'sessions.tv', 'sessions.summary'] as $route) {
            $this->getJson(route($route, $state['id']))->assertNotFound();
        }
        $this->command($state, 'start')->assertNotFound();
        $this->postJson(route('sessions.lease', $state['id']), ['client_id' => (string) Str::uuid()])->assertNotFound();
    }

    public function test_duplicate_start_with_different_command_id_is_rejected_by_state_machine(): void
    {
        [, $state] = $this->sessionFor(User::factory()->create());
        $state = $this->command($state, 'start')->assertOk()->json('data');
        $this->command($state, 'start')->assertUnprocessable();
        $this->assertDatabaseCount('workout_sessions', 1);
        $this->assertDatabaseCount('session_commands', 1);
    }

    public function test_timer_foreign_key_rejects_a_session_from_another_box(): void
    {
        [, $state] = $this->sessionFor(User::factory()->create());
        $foreign = User::factory()->create();
        app(CurrentBox::class)->set($foreign->box_id);
        $this->expectException(QueryException::class);
        TimerSession::factory()->create(['workout_session_id' => $state['id']]);
    }
}
