<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\TrainingModality;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithWorkouts;
use Tests\TestCase;

class BoxSettingsTest extends TestCase
{
    use InteractsWithWorkouts, RefreshDatabase;

    private function input(): array
    {
        return ['modalities' => TrainingModality::pluck('id')->all(), 'name' => 'Box Personalizada', 'head_coach_name' => 'Coach Ana', 'administrator_name' => 'Admin João', 'timezone' => 'Europe/Lisbon', 'primary_color' => '#F97316', 'secondary_color' => '#334155'];
    }

    private function logo(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('logo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jFJkAAAAASUVORK5CYII='));
    }

    public function test_admin_can_upload_replace_and_remove_logo_without_leaving_old_files(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user)->put(route('box.settings.update'), [...$this->input(), 'logo' => $this->logo()])->assertSessionHasNoErrors();
        $first = $user->box()->first()->logo_path;
        $this->assertStringStartsWith("boxes/{$user->box_id}/", $first);
        Storage::disk('public')->assertExists($first);
        $this->put(route('box.settings.update'), [...$this->input(), 'logo' => $this->logo()])->assertSessionHasNoErrors();
        $second = $user->box()->first()->logo_path;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
        $this->get(route('box.logo'))->assertOk();
        $this->put(route('box.settings.update'), [...$this->input(), 'remove_logo' => 1])->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($second);
        $this->assertNull($user->box()->first()->logo_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_colors_and_identity_persist_on_refresh_dashboard_workout_and_preview(): void
    {
        $user = User::factory()->create();
        $workout = $this->createWorkoutFor($user);
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Configurações da Box');
        $this->put(route('box.settings.update'), $this->input())->assertSessionHasNoErrors()->assertRedirect(route('box.settings'));
        $this->assertDatabaseHas('boxes', ['id' => $user->box_id, 'primary_color' => '#F97316', 'secondary_color' => '#334155', 'timezone' => 'Europe/Lisbon']);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Admin João']);
        foreach (['box.settings' => [], 'dashboard' => [], 'workouts.show' => $workout, 'workouts.preview' => $workout] as $route => $parameters) {
            $this->get(route($route, $parameters))->assertOk()->assertSee('--box-primary:#F97316', false)->assertSee('--box-secondary:#334155', false)->assertSee('Box Personalizada');
        }
    }

    public function test_settings_cannot_change_another_box_or_username_role(): void
    {
        $user = User::factory()->create();
        $foreign = User::factory()->create();
        $this->actingAs($user)->put(route('box.settings.update'), [...$this->input(), 'box_id' => $foreign->box_id, 'user_id' => $foreign->id, 'role' => 'superadmin', 'username' => 'changed'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('boxes', ['id' => $foreign->box_id, 'name' => $foreign->box->name]);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'username' => $user->username, 'role' => 'admin']);
    }

    public function test_coach_and_guest_cannot_update_settings(): void
    {
        $this->get(route('box.settings'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'coach']))->get(route('box.settings'))->assertForbidden();
        $this->put(route('box.settings.update'), $this->input())->assertForbidden();
    }

    public function test_invalid_colors_timezone_non_image_and_oversized_logo_are_rejected(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create())->put(route('box.settings.update'), [...$this->input(), 'primary_color' => 'red; color:black', 'timezone' => 'Invalid/Zone', 'logo' => UploadedFile::fake()->create('attack.svg', 1, 'image/svg+xml')])->assertSessionHasErrors(['primary_color', 'timezone', 'logo']);
        $this->put(route('box.settings.update'), [...$this->input(), 'logo' => UploadedFile::fake()->create('huge.png', 2049, 'image/png')])->assertSessionHasErrors('logo');
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_failed_database_update_keeps_old_logo_and_removes_new_upload(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user)->put(route('box.settings.update'), [...$this->input(), 'logo' => $this->logo()])->assertSessionHasNoErrors();
        $old = $user->box()->first()->logo_path;
        Box::saving(function (): void {
            throw new \RuntimeException('Simulated failure');
        });
        $this->withoutExceptionHandling();
        try {
            $this->put(route('box.settings.update'), [...$this->input(), 'logo' => $this->logo()]);
            $this->fail('Expected failure.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Simulated failure', $error->getMessage());
        }
        $this->assertSame([$old], Storage::disk('public')->allFiles());
        $this->assertDatabaseHas('boxes', ['id' => $user->box_id, 'logo_path' => $old]);
    }

    public function test_theme_and_logo_survive_logout_and_login_again(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user)->put(route('box.settings.update'), [...$this->input(), 'logo' => $this->logo()])->assertSessionHasNoErrors();
        $this->post(route('logout'))->assertRedirect();
        $this->post(route('login.store'), ['username' => $user->username, 'password' => 'password'])->assertSessionHasNoErrors();
        $this->get(route('dashboard'))->assertOk()->assertSee('--box-primary:#F97316', false)->assertSee('dashboard-box-logo')->assertSee('Box Personalizada');
    }

    public function test_invalid_array_input_does_not_break_settings_after_validation(): void
    {
        $this->actingAs(User::factory()->create())->from(route('box.settings'))->put(route('box.settings.update'), [...$this->input(), 'name' => ['invalid'], 'primary_color' => ['invalid']])->assertSessionHasErrors(['name', 'primary_color']);
        $this->get(route('box.settings'))->assertOk()->assertSee('Salvar configurações');
    }
}
