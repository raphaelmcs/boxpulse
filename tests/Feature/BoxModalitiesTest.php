<?php

namespace Tests\Feature;

use App\Models\TrainingModality;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithSessions;
use Tests\TestCase;

class BoxModalitiesTest extends TestCase
{
    use InteractsWithSessions, RefreshDatabase;

    private function ids(array $slugs): array
    {
        return TrainingModality::whereIn('slug', $slugs)->orderBy('id')->pluck('id')->all();
    }

    private function settings(User $user, array $ids): array
    {
        return ['name' => $user->box->name, 'head_coach_name' => $user->box->head_coach_name, 'administrator_name' => $user->name, 'timezone' => 'America/Sao_Paulo', 'primary_color' => '#C8FA3C', 'secondary_color' => '#38BDF8', 'modalities' => $ids];
    }

    public static function selections(): array
    {
        return ['one' => [['crossfit']], 'two' => [['crossfit', 'hyrox']], 'three' => [['crossfit', 'hyrox', 'functional']]];
    }

    #[DataProvider('selections')]
    public function test_onboarding_persists_only_selected_modalities(array $slugs): void
    {
        $ids = $this->ids($slugs);
        $this->get(route('register'))->assertOk()->assertSee('Quais modalidades sua box oferece?');
        $this->post(route('register.store'), ['box_name' => 'Nova Box', 'head_coach_name' => 'Head', 'name' => 'Admin', 'username' => 'nova.admin', 'password' => 'StrongPassword123', 'password_confirmation' => 'StrongPassword123', 'modalities' => $ids])->assertSessionHasNoErrors()->assertRedirect(route('workspace'));
        $user = User::where('username', 'nova.admin')->firstOrFail();
        $this->assertSame($ids, $user->box->modalities()->orderBy('training_modalities.id')->pluck('training_modalities.id')->all());
        $dashboard = $this->get(route('dashboard'))->assertOk();
        foreach (['crossfit', 'hyrox', 'functional'] as $slug) {
            $needle = 'modality-card modality-'.$slug;
            if (in_array($slug, $slugs)) {
                $dashboard->assertSee($needle, false);
            } else {
                $dashboard->assertDontSee($needle, false);
            }
        }
    }

    public function test_no_invalid_or_duplicate_selection_can_register_a_box(): void
    {
        $input = ['box_name' => 'Nova Box', 'head_coach_name' => 'Head', 'name' => 'Admin', 'username' => 'nova.admin', 'password' => 'StrongPassword123', 'password_confirmation' => 'StrongPassword123'];
        foreach ([[], [9999], [1, 1], ['crossfit'], [['id' => 1]]] as $selection) {
            $this->post(route('register.store'), [...$input, 'modalities' => $selection])->assertSessionHasErrors();
            $this->assertDatabaseCount('boxes', 0);
        }
        $this->post(route('register.store'), $input)->assertSessionHasErrors('modalities');
        $this->get(route('register'))->assertOk();
    }

    public function test_onboarding_preserves_identity_logo_colors_and_timezone(): void
    {
        Storage::fake('public');
        $logo = UploadedFile::fake()->createWithContent('logo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jFJkAAAAASUVORK5CYII='));
        $input = ['box_name' => 'Nova Box', 'head_coach_name' => 'Head', 'name' => 'Admin', 'username' => 'nova.admin', 'password' => 'StrongPassword123', 'password_confirmation' => 'StrongPassword123', 'modalities' => $this->ids(['hyrox']), 'timezone' => 'Europe/Lisbon', 'primary_color' => '#F97316', 'secondary_color' => '#334155', 'logo' => $logo];
        $this->post(route('register.store'), $input)->assertSessionHasNoErrors();
        $box = User::where('username', 'nova.admin')->firstOrFail()->box;
        $this->assertSame('Europe/Lisbon', $box->timezone);
        $this->assertSame('#F97316', $box->primary_color);
        Storage::disk('public')->assertExists($box->logo_path);
        $this->get(route('box.logo'))->assertOk();
    }

    public function test_settings_disable_preserves_records_reactivation_restores_operations_and_other_boxes(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $workout = $this->createWorkoutFor($user, [...$this->workoutInput('hyrox'), 'scheduled_for' => now()->toDateString()]);
        $this->actingAs($user)->put(route('box.settings.update'), [...$this->settings($user, $this->ids(['crossfit'])), 'box_id' => $other->box_id])->assertSessionHasNoErrors();
        $this->assertCount(3, $other->box->modalities);
        $this->assertSame($this->ids(['crossfit']), $user->box->modalities()->pluck('training_modalities.id')->all());
        $this->get(route('dashboard'))->assertOk()->assertDontSee('modality-card modality-hyrox', false)->assertDontSee($workout->title);
        $this->get(route('workouts.show', $workout))->assertOk()->assertSee('Modalidade desativada')->assertSee($workout->title)->assertDontSee('Criar cópia');
        $this->get(route('schedule', ['date' => $workout->scheduled_for->toDateString()]))->assertOk()->assertSee($workout->title)->assertSee('Modalidade desativada');
        $this->get(route('workouts.edit', $workout))->assertOk()->assertSee('Modalidade desativada');
        $this->get(route('workouts.preview', $workout))->assertOk()->assertSee('Modalidade desativada')->assertDontSee('Iniciar apresentação ↗');
        $this->get(route('workouts.create', ['modality' => 'hyrox']))->assertNotFound();
        $this->get(route('modalities.show', ['modality' => 'hyrox']))->assertNotFound();
        $this->get(route('workouts.create'))->assertOk()->assertSee('CrossFit');
        $this->post(route('workouts.store'), $this->workoutInput('hyrox'))->assertSessionHasErrors('training_modality_id');
        $this->post(route('workouts.publish', $workout))->assertSessionHasErrors('training_modality_id');
        $this->post(route('workouts.copy', $workout), ['scheduled_for' => '2026-12-01'])->assertSessionHasErrors('training_modality_id');
        $this->post(route('sessions.store', $workout))->assertSessionHasErrors('training_modality_id');
        $this->put(route('workouts.update', $workout), $this->workoutInput('hyrox'))->assertSessionHasErrors('training_modality_id');
        $this->put(route('workouts.update', $workout), $this->workoutInput('hyrox', 'main', 'draft'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('workout_blocks', ['workout_id' => $workout->id, 'description' => $this->workoutInput('hyrox')['blocks'][0]['description']]);
        $this->put(route('box.settings.update'), $this->settings($user, $this->ids(['crossfit', 'hyrox'])))->assertSessionHasNoErrors();
        $this->post(route('workouts.publish', $workout))->assertSessionHasNoErrors();
        $this->post(route('sessions.store', $workout))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('workouts', 1);
    }

    public function test_disabled_historical_modality_is_identified_in_history(): void
    {
        $user = User::factory()->create();
        $workout = $this->createWorkoutFor($user, [...$this->workoutInput('hyrox'), 'scheduled_for' => '2001-01-01']);
        $user->box->modalities()->sync($this->ids(['crossfit']));
        $this->actingAs($user)->get(route('workouts.index'))->assertOk()->assertSee($workout->title)->assertSee('Modalidade desativada');
    }

    public function test_settings_require_at_least_one_active_modality_and_reject_coaches(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->put(route('box.settings.update'), $this->settings($user, []))->assertSessionHasErrors('modalities');
        $this->assertCount(3, $user->box->modalities);
        $this->put(route('box.settings.update'), $this->settings($user, [9999]))->assertSessionHasErrors('modalities.0');
        $this->actingAs(User::factory()->create(['box_id' => $user->box_id, 'role' => 'coach']))->put(route('box.settings.update'), $this->settings($user, [1]))->assertForbidden();
        $this->get(route('box.settings'))->assertForbidden();
    }

    public function test_started_session_survives_disabling_and_pending_cannot_start_or_be_reprepared(): void
    {
        $user = User::factory()->create();
        [$workout, $state] = $this->sessionFor($user, $this->workoutInput('hyrox', 'full'));
        $state = $this->command($state, 'start')->assertOk()->json('data');
        $this->put(route('box.settings.update'), $this->settings($user, $this->ids(['crossfit'])))->assertSessionHasNoErrors();
        $this->post(route('sessions.store', $workout))->assertRedirect(route('sessions.control', $state['id']));
        $state = $this->command($state, 'pause')->assertOk()->assertJsonPath('data.source.modality_enabled', false)->json('data');
        $state = $this->command($state, 'resume')->assertOk()->json('data');
        $state = $this->command($state, 'next')->assertOk()->json('data');
        $this->get(route('sessions.tv', $state['id']))->assertOk();
        $this->command($state, 'finish')->assertOk();
        $this->post(route('sessions.store', $workout))->assertSessionHasErrors('training_modality_id');
        $this->assertDatabaseCount('workout_sessions', 1);

        $this->put(route('box.settings.update'), $this->settings($user, $this->ids(['hyrox'])))->assertSessionHasNoErrors();
        [$pendingWorkout, $pending] = $this->sessionFor($user, $this->workoutInput('hyrox'));
        $this->put(route('box.settings.update'), $this->settings($user, $this->ids(['crossfit'])))->assertSessionHasNoErrors();
        $this->command($pending, 'start')->assertConflict();
        $this->command($pending, 'next')->assertConflict();
        $this->post(route('sessions.store', $pendingWorkout))->assertSessionHasErrors('training_modality_id');
        $this->command($pending, 'cancel')->assertOk();
    }

    public function test_week_copy_only_copies_active_modalities_and_preserves_all_sources(): void
    {
        $user = User::factory()->create();
        $active = $this->createWorkoutFor($user, $this->workoutInput('crossfit'));
        $disabled = $this->createWorkoutFor($user, $this->workoutInput('hyrox'));
        $user->box->modalities()->sync($this->ids(['crossfit']));
        $this->actingAs($user)->post(route('schedule.copy'), ['source_date' => '2026-11-10', 'target_date' => '2026-11-17', 'conflict_policy' => 'append', 'confirmation' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('workouts', 3);
        $this->assertDatabaseHas('workouts', ['id' => $disabled->id, 'status' => 'published']);
        $this->assertDatabaseHas('workouts', ['title' => $active->title, 'scheduled_for' => '2026-11-17', 'status' => 'draft']);
        $this->assertDatabaseMissing('workouts', ['title' => $disabled->title, 'scheduled_for' => '2026-11-17']);
    }

    public function test_legacy_backfill_enables_all_once_without_duplicates_or_workout_changes(): void
    {
        $user = User::factory()->create();
        $workout = $this->createWorkoutFor($user);
        $user->box->modalities()->detach();
        $migration = require database_path('migrations/2026_11_10_153006_create_box_modalities_table.php');
        $migration->backfillExistingBoxes();
        $migration->backfillExistingBoxes();
        $this->assertDatabaseCount('box_modalities', 3);
        $this->assertDatabaseHas('workouts', ['id' => $workout->id, 'content_version' => 1, 'status' => 'published']);
        $user->box->modalities()->sync($this->ids(['crossfit']));
        $migration->up();
        $this->assertDatabaseCount('box_modalities', 1);
    }
}
