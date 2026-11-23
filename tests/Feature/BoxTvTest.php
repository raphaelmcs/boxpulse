<?php

namespace Tests\Feature;

use App\Models\User;
use App\Tenancy\CurrentBox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithSessions;
use Tests\TestCase;

class BoxTvTest extends TestCase
{
    use InteractsWithSessions, RefreshDatabase;

    private function grant(User $admin): string
    {
        $this->actingAs($admin)->post(route('box.tv.rotate'))->assertSessionHasNoErrors()->assertRedirect(route('box.settings'));

        return $admin->box()->firstOrFail()->tv_access_token;
    }

    private function tv(string $token, array $headers = []): TestResponse
    {
        return $this->getJson(route('box.tv.state'), ['Authorization' => 'Bearer '.$token, ...$headers]);
    }

    public function test_admin_can_create_copy_and_rotate_an_encrypted_unique_link(): void
    {
        $admin = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($admin)->get(route('box.settings'))->assertOk()->assertSee('TV do Box')->assertSee('Gerar link da TV');
        $old = $this->grant($admin);
        $different = $this->grant($other);
        $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $old);
        $this->assertNotSame($old, $different);
        $box = $admin->box()->firstOrFail();
        $this->assertSame(hash('sha256', $old), $box->tv_access_hash);
        $this->assertNotSame($old, $box->getRawOriginal('tv_access_token'));
        $this->assertArrayNotHasKey('tv_access_token', $box->toArray());
        $this->assertArrayNotHasKey('tv_access_hash', $box->toArray());
        $settings = $this->actingAs($admin)->get(route('box.settings'))->assertOk()
            ->assertSee('Copiar link')->assertSee('Abrir TV')->assertSee('Gerar novo link')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertTrue(str_contains($settings->getContent(), route('box.tv.open').'#'.$old));
        $this->assertFalse(str_contains($settings->getContent(), $different));
        $this->post(route('box.tv.rotate'))->assertSessionHasErrors('confirmation');
        $this->tv($old)->assertOk();
        $this->post(route('box.tv.rotate'), ['confirmation' => '1', 'box_id' => $other->box_id])->assertSessionHasNoErrors();
        $new = $admin->box()->firstOrFail()->tv_access_token;
        $this->assertNotSame($old, $new);
        $this->tv($old)->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->tv($new)->assertOk();
        $this->tv($different)->assertOk()->assertJsonPath('box.name', $other->box->name);
    }

    public function test_landing_is_cookie_free_and_bearer_authentication_is_limited_to_public_gets(): void
    {
        $admin = User::factory()->create();
        $token = $this->grant($admin);
        [$workout, $state] = $this->sessionFor($admin);
        $this->app['auth']->forgetGuards();
        $this->get(route('box.tv.open'))->assertOk()->assertSee('Aguardando o coach iniciar a aula')
            ->assertSee('data-view="box-tv"', false)->assertDontSee('Painel do coach')->assertDontSee('data-command-url', false)
            ->assertDontSee($token)->assertHeaderMissing('Set-Cookie')->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->getJson(route('box.tv.state', ['token' => $token]))->assertNotFound();
        $this->tv(str_repeat('9', 64))->assertNotFound();
        $this->tv('malformado')->assertNotFound();
        $this->tv($token)->assertOk()->assertHeaderMissing('Set-Cookie')->assertJsonPath('status', 'waiting');
        $authorization = ['Authorization' => 'Bearer '.$token];
        $this->getJson(route('box.tv.presence'), $authorization)->assertUnauthorized();
        $this->getJson(route('sessions.state', $state['id']), $authorization)->assertUnauthorized();
        $this->getJson(route('workouts.edit', $workout), $authorization)->assertUnauthorized();
        $this->postJson(route('sessions.commands', $state['id']), ['action' => 'start'], $authorization)->assertUnauthorized();
        $this->postJson(route('sessions.lease', $state['id']), [], $authorization)->assertUnauthorized();
        $this->postJson(route('box.tv.rotate'), ['confirmation' => '1'], $authorization)->assertUnauthorized();
        $this->postJson(route('box.tv.state'), [], $authorization)->assertMethodNotAllowed();
        $this->assertDatabaseCount('workout_sessions', 1);
        $this->assertDatabaseCount('session_commands', 0);
        $this->assertFalse(auth()->check());
    }

    public function test_coach_cannot_manage_a_public_tv_link_or_see_its_presence(): void
    {
        $admin = User::factory()->create();
        $this->grant($admin);
        $coach = User::factory()->create(['box_id' => $admin->box_id, 'role' => 'coach']);
        $this->actingAs($coach)->get(route('box.settings'))->assertForbidden();
        $this->post(route('box.tv.rotate'), ['confirmation' => '1'])->assertForbidden();
        $this->getJson(route('box.tv.presence'))->assertForbidden();
    }

    public function test_boxes_never_share_snapshots_or_logos_and_public_data_excludes_control_metadata(): void
    {
        Storage::fake('public');
        $this->travelTo(Carbon::parse('2026-11-23 15:00:00', 'UTC'));
        $admin = User::factory()->create();
        $other = User::factory()->create();
        $token = $this->grant($admin);
        $otherToken = $this->grant($other);
        $path = "boxes/{$admin->box_id}/logo.png";
        $otherPath = "boxes/{$other->box_id}/other.png";
        foreach ([$path, $otherPath] as $file) {
            Storage::disk('public')->put($file, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jFJkAAAAASUVORK5CYII='));
        }
        $admin->box()->firstOrFail()->update(['name' => 'Box Azul', 'logo_path' => $path, 'primary_color' => '#334455', 'timezone' => 'Europe/Lisbon']);
        $other->box()->firstOrFail()->update(['name' => 'Box Laranja', 'logo_path' => $otherPath]);
        $input = [...$this->workoutInput(), 'title' => 'Aula Azul'];
        $input['blocks'][0]['load_entries'] = [['equipment' => 'Dumbbell', 'rx' => '22 / 16', 'unit' => 'kg']];
        $input['blocks'][0]['text_style'] = ['title' => ['font_weight' => 'bold', 'text_color' => '#FACC15']];
        [, $session] = $this->sessionFor($admin, $input);
        $this->command($session, 'start')->assertOk();
        $response = $this->tv($token)->assertOk()->assertJsonPath('status', 'active')->assertJsonPath('box.name', 'Box Azul')
            ->assertJsonPath('box.timezone', 'Europe/Lisbon')->assertJsonPath('box.logo_version', hash('sha256', $path))
            ->assertJsonPath('data.title', 'Aula Azul')->assertJsonPath('data.timers.0.state', 'preparing')
            ->assertJsonPath('data.timers.0.load_entries.0.rx', '22 / 16')->assertJsonPath('data.timers.0.text_style.title.text_color', '#FACC15')
            ->assertJsonMissingPath('data.source')->assertJsonMissingPath('data.can_control')->assertJsonMissingPath('data.controller_client_id')
            ->assertJsonMissingPath('box.head_coach_name')->assertJsonMissingPath('box.id')->assertDontSee($token)->assertDontSee($path);
        $this->assertStringContainsString('--box-primary:#334455', $response->json('box.theme'));
        $this->tv($otherToken)->assertOk()->assertJsonPath('status', 'waiting')->assertJsonPath('data', null)->assertDontSee('Aula Azul');
        $own = $this->get(route('box.tv.logo', ['box_id' => $other->box_id]), ['Authorization' => 'Bearer '.$token])
            ->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame(Storage::disk('public')->path($path), $own->baseResponse->getFile()->getPathname());
        $this->get(route('box.tv.logo'), ['Authorization' => 'Bearer '.$otherToken])->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get(route('box.tv.logo'))->assertNotFound();
        $admin->box()->firstOrFail()->update(['logo_path' => $otherPath]);
        $this->get(route('box.tv.logo'), ['Authorization' => 'Bearer '.$token])->assertNotFound();
        $this->assertNull(app(CurrentBox::class)->id());
    }

    public function test_tv_tracks_authoritative_states_and_returns_to_waiting_after_a_class(): void
    {
        $this->travelTo(Carbon::parse('2026-11-23 15:00:00', 'UTC'));
        $admin = User::factory()->create();
        $token = $this->grant($admin);
        $input = $this->workoutInput('crossfit', 'full');
        $input['blocks'][0] = ['type' => 'warmup', 'title' => 'Intervalos', 'timer_type' => 'tabata', 'timer_settings' => ['work_seconds' => 2, 'rest_seconds' => 2, 'rounds' => 2]];
        [, $state] = $this->sessionFor($admin, $input, ['preparation_seconds' => 1]);
        $waiting = $this->tv($token)->assertOk()->assertJsonPath('status', 'waiting')->assertJsonPath('data', null);
        $this->tv($token, ['If-None-Match' => $waiting->headers->get('ETag')])->assertStatus(304)->assertHeader('X-Server-Time-Ms', (string) now()->getTimestampMs());
        $state = $this->command($state, 'start')->assertOk()->json('data');
        $this->tv($token)->assertOk()->assertJsonPath('data.timers.0.state', 'preparing');
        $this->travel(1)->seconds();
        $state = $this->tv($token)->assertOk()->assertJsonPath('data.timers.0.state', 'running')->json('data') + ['controller_client_id' => $state['controller_client_id']];
        $state = $this->command($state, 'pause')->assertOk()->json('data');
        $this->tv($token)->assertOk()->assertJsonPath('data.timers.0.state', 'paused');
        $state = $this->command($state, 'resume')->assertOk()->json('data');
        $this->travel(2)->seconds();
        $this->tv($token)->assertOk()->assertJsonPath('data.timers.0.config.segments.1.phase', 'rest');
        $this->travel(4)->seconds();
        $ended = $this->tv($token)->assertOk()->assertJsonPath('data.timers.0.state', 'completed');
        $this->tv($token, ['If-None-Match' => $ended->headers->get('ETag')])->assertStatus(304);
        $state = $this->postJson(route('sessions.lease', $state['id']), ['client_id' => $state['controller_client_id']])->assertOk()->json('data');
        $state = $this->command($state, 'next')->assertOk()->json('data');
        $this->tv($token)->assertOk()->assertJsonPath('data.current_position', 1);
        $this->command($this->completeLastStage($state), 'finish')->assertOk();
        $this->tv($token)->assertOk()->assertJsonPath('status', 'ended')->assertJsonPath('data', null);
        $this->travel(9)->seconds();
        $this->tv($token)->assertOk()->assertJsonPath('status', 'waiting')->assertJsonPath('data', null);
        $this->assertDatabaseCount('session_commands', 7);
        $this->assertDatabaseCount('workout_sessions', 1);
    }

    public function test_presence_requires_a_successful_poll_and_expires_or_resets_on_rotation(): void
    {
        $this->travelTo(Carbon::parse('2026-11-23 15:00:00', 'UTC'));
        $admin = User::factory()->create();
        $token = $this->grant($admin);
        $this->get(route('box.tv.open'))->assertOk();
        $this->getJson(route('box.tv.presence'))->assertExactJson(['online' => false, 'last_seen_at' => null]);
        $this->tv($token)->assertOk();
        $this->getJson(route('box.tv.presence'))->assertJsonPath('online', true)->assertJsonPath('last_seen_at', now($admin->box->timezone)->format('d/m H:i:s'));
        $this->travel(26)->seconds();
        $this->getJson(route('box.tv.presence'))->assertJsonPath('online', false);
        $this->tv($token)->assertOk();
        $this->getJson(route('box.tv.presence'))->assertJsonPath('online', true);
        $this->post(route('box.tv.rotate'), ['confirmation' => '1'])->assertSessionHasNoErrors();
        $this->getJson(route('box.tv.presence'))->assertExactJson(['online' => false, 'last_seen_at' => null]);
    }

    public function test_latest_started_class_is_selected_and_older_class_does_not_reappear_after_it_finishes(): void
    {
        $this->travelTo(Carbon::parse('2026-11-23 15:00:00', 'UTC'));
        $admin = User::factory()->create();
        [, $old] = $this->sessionFor($admin, [...$this->workoutInput(), 'title' => 'Aula anterior']);
        $this->command($old, 'start')->assertOk();
        $token = $this->grant($admin);
        $this->travel(1)->seconds();

        [, $latest] = $this->sessionFor($admin, [...$this->workoutInput(), 'title' => 'Aula mais recente']);
        $this->tv($token)->assertOk()->assertJsonPath('data.title', 'Aula anterior');
        $latest = $this->command($latest, 'start')->assertOk()->json('data');
        $this->tv($token)->assertOk()->assertJsonPath('data.title', 'Aula mais recente');
        $this->command($this->completeLastStage($latest), 'finish')->assertOk();
        $this->tv($token)->assertOk()->assertJsonPath('status', 'ended')->assertJsonPath('data', null);
        $this->travel(9)->seconds();
        $this->tv($token)->assertOk()->assertJsonPath('status', 'waiting')->assertJsonPath('data', null);
        $this->assertDatabaseHas('workout_sessions', ['id' => $old['id'], 'status' => 'active']);
    }
}
