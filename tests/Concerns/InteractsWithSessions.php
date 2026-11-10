<?php

namespace Tests\Concerns;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

trait InteractsWithSessions
{
    use InteractsWithWorkouts;

    private function sessionFor(User $user, ?array $input = null, array $options = []): array
    {
        $workout = $this->createWorkoutFor($user, $input);
        $response = $this->actingAs($user)->post(route('sessions.store', $workout), $options)->assertSessionHasNoErrors()->assertRedirect();
        preg_match('~/sessions/(\d+)/control$~', $response->headers->get('Location'), $matches);
        $snapshot = $this->postJson(route('sessions.lease', $matches[1]), ['client_id' => (string) Str::uuid()])->assertOk()->json('data');

        return [$workout, $snapshot];
    }

    private function command(array $snapshot, string $action, array $extra = []): TestResponse
    {
        return $this->postJson(route('sessions.commands', $snapshot['id']), [...['client_id' => $snapshot['controller_client_id'], 'command_id' => (string) Str::uuid(), 'expected_version' => $snapshot['version'], 'action' => $action], ...$extra]);
    }
}
