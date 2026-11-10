<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_view_login_and_registration(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Entre na sua box.');
        $this->get(route('register'))->assertOk()->assertSee('Nome do Head Coach');
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_login_uses_a_normalized_username_and_rotates_session(): void
    {
        $user = User::factory()->create(['username' => 'coach.login']);
        $this->get(route('login'));
        $before = session()->getId();
        $this->post(route('login.store'), ['username' => ' COACH.LOGIN ', 'password' => 'password'])->assertRedirect(route('workspace'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($before, session()->getId());
    }

    public function test_invalid_credentials_are_rejected_with_a_generic_message(): void
    {
        $user = User::factory()->create();
        $this->post(route('login.store'), ['username' => $user->username, 'password' => 'wrong'])->assertSessionHasErrors(['username' => 'Username ou senha inválidos.']);
        $this->assertGuest();
    }

    public function test_credentials_are_throttled_after_five_failed_attempts(): void
    {
        $user = User::factory()->create(['username' => 'limited.coach']);
        $key = 'login:'.hash('sha256', $user->username.'|127.0.0.1');
        RateLimiter::clear($key);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), ['username' => $user->username, 'password' => 'wrong'])->assertSessionHasErrors('username');
        }
        $this->post(route('login.store'), ['username' => $user->username, 'password' => 'password'])->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_logout_invalidates_the_authenticated_session(): void
    {
        $this->actingAs(User::factory()->create())->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_legacy_users_without_a_box_cannot_login_or_access_dashboard(): void
    {
        $user = User::factory()->create(['box_id' => null]);
        $this->post(route('login.store'), ['username' => $user->username, 'password' => 'password'])->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
    }

    public function test_malformed_credentials_are_validation_errors(): void
    {
        $this->post(route('login.store'), ['username' => ['unexpected'], 'password' => 'password'])->assertSessionHasErrors('username');
        $this->assertGuest();
    }
}
