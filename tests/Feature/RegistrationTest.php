<?php

namespace Tests\Feature;

use App\Actions\RegisterBox;
use App\Models\Box;
use App\Models\TrainingModality;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function registration(array $overrides = []): array
    {
        return array_replace(['modalities' => TrainingModality::pluck('id')->all(), 'box_name' => 'Box Horizonte', 'head_coach_name' => 'Ana Coach', 'name' => 'Bruno Admin', 'username' => 'horizonte.admin', 'password' => 'StrongPassword123', 'password_confirmation' => 'StrongPassword123'], $overrides);
    }

    public function test_registration_creates_a_box_and_its_admin_atomically(): void
    {
        $this->post(route('register.store'), $this->registration(['box_id' => 999, 'role' => 'superadmin']))->assertRedirect(route('workspace'));

        $user = User::where('username', 'horizonte.admin')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('StrongPassword123', $user->password));
        $this->assertNotSame('StrongPassword123', $user->getRawOriginal('password'));
        $this->assertSame('admin', $user->role);
        $this->assertSame('America/Sao_Paulo', $user->box->timezone);
        $this->assertSame('Box Horizonte', $user->box->name);
        $this->assertDatabaseCount('boxes', 1);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_username_is_normalized_before_storage_and_uniqueness_validation(): void
    {
        User::factory()->create(['username' => 'coach.admin']);
        $this->post(route('register.store'), $this->registration(['username' => ' COACH.ADMIN ']))->assertSessionHasErrors('username');
        $this->assertDatabaseCount('boxes', 1);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_invalid_registration_does_not_leave_an_orphan_box(): void
    {
        $this->post(route('register.store'), $this->registration(['password_confirmation' => 'different', 'username' => '<script>']))->assertSessionHasErrors(['username', 'password']);
        $this->assertDatabaseCount('boxes', 0);
        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_failed_user_insert_rolls_back_the_box(): void
    {
        User::factory()->create(['username' => 'horizonte.admin']);
        try {
            app(RegisterBox::class)->handle($this->registration());
            $this->fail('A duplicate username must be rejected by the database.');
        } catch (UniqueConstraintViolationException) {
            $this->assertSame(1, Box::count());
            $this->assertSame(1, User::count());
        }
    }

    public function test_password_requires_length_mixed_case_and_numbers(): void
    {
        $this->post(route('register.store'), $this->registration(['password' => 'short', 'password_confirmation' => 'short']))->assertSessionHasErrors('password');
        $this->assertDatabaseCount('boxes', 0);
    }

    public function test_authenticated_users_cannot_register_another_box(): void
    {
        $this->actingAs(User::factory()->create())->post(route('register.store'), $this->registration())->assertRedirect(route('workspace'));
        $this->assertDatabaseCount('boxes', 1);
    }

    public function test_passwords_over_the_bcrypt_byte_limit_are_rejected(): void
    {
        $password = 'Strong123'.str_repeat('á', 34);
        $this->post(route('register.store'), $this->registration(['password' => $password, 'password_confirmation' => $password]))->assertSessionHasErrors('password');
        $this->assertDatabaseCount('boxes', 0);
    }

    public function test_malformed_username_is_a_validation_error(): void
    {
        $this->post(route('register.store'), $this->registration(['username' => ['unexpected']]))->assertSessionHasErrors('username');
        $this->assertDatabaseCount('boxes', 0);
    }

    public function test_password_with_a_null_byte_is_rejected(): void
    {
        $password = "StrongPassword123\0";
        $this->post(route('register.store'), $this->registration(['password' => $password, 'password_confirmation' => $password]))->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 0);
    }
}
