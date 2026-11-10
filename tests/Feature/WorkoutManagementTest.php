<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithWorkouts;
use Tests\TestCase;

class WorkoutManagementTest extends TestCase
{
    use InteractsWithWorkouts, RefreshDatabase;

    public function test_full_crossfit_workout_is_created_with_ordered_blocks_and_author(): void
    {
        $user = User::factory()->create();
        $data = $this->workoutInput('crossfit', 'full');
        $this->actingAs($user)->post(route('workouts.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('workouts', ['box_id' => $user->box_id, 'created_by' => $user->id, 'title' => $data['title'], 'structure' => 'full']);
        $this->assertDatabaseCount('workout_blocks', 3);
        $this->assertDatabaseHas('workout_blocks', ['type' => 'warmup', 'position' => 0]);
        $this->assertDatabaseHas('workout_blocks', ['type' => 'skill', 'position' => 1, 'title' => 'Snatch livre']);
        $this->assertDatabaseHas('workout_blocks', ['type' => 'workout', 'position' => 2, 'description' => $data['blocks'][2]['description']]);
    }

    public function test_main_only_workout_does_not_require_warmup_or_skill_and_title_is_optional(): void
    {
        $data = $this->workoutInput();
        $data['title'] = null;
        $data['blocks'][0]['title'] = null;
        $this->actingAs(User::factory()->create())->post(route('workouts.store'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('workouts', ['title' => 'Treino de CrossFit']);
        $this->assertDatabaseCount('workout_blocks', 1);
    }

    public function test_hyrox_supports_eight_stations_and_also_a_different_station_count(): void
    {
        $user = User::factory()->create();
        $data = $this->workoutInput('hyrox', 'full');
        $data['blocks'][2]['stations'] = array_map(fn ($name) => ['name' => $name, 'distance' => 1000, 'load' => 20, 'repetitions' => 10], ['SkiErg', 'Sled Push', 'Sled Pull', 'Burpee Broad Jumps', 'Row', 'Farmers Carry', 'Sandbag Lunges', 'Wall Balls']);
        $this->actingAs($user)->post(route('workouts.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('workout_stations', 8);
        $data['blocks'][2]['stations'] = [['name' => 'Running', 'distance' => 500]];
        $this->post(route('workouts.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('workout_stations', 9);
    }

    public function test_functional_circuit_saves_station_order_and_work_rest_transition_settings(): void
    {
        $data = $this->workoutInput('functional');
        $data['blocks'][0] = ['type' => 'workout', 'timer_type' => 'circuit', 'timer_settings' => ['work_seconds' => 40, 'rest_seconds' => 20, 'transition_seconds' => 5, 'rounds' => 4], 'stations' => [['name' => 'Remo', 'duration_seconds' => 35], ['name' => 'Agachamento', 'load' => 12.5]]];
        $this->actingAs(User::factory()->create())->post(route('workouts.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('workout_stations', ['position' => 0, 'name' => 'Remo', 'duration_seconds' => 35]);
        $this->assertDatabaseHas('workout_stations', ['position' => 1, 'name' => 'Agachamento', 'load' => 12.5]);
        $this->assertDatabaseHas('workout_blocks', ['timer_settings->rounds' => 4, 'timer_settings->version' => 1, 'timer_settings->transition_seconds' => 5]);
    }

    public function test_incomplete_draft_can_be_saved_but_not_published_or_prepared(): void
    {
        $user = User::factory()->create();
        $data = $this->workoutInput(status: 'draft');
        $data['blocks'] = [];
        $this->actingAs($user)->post(route('workouts.store'), $data)->assertSessionHasNoErrors();
        $id = DB::table('workouts')->value('id');
        $this->post(route('workouts.publish', $id))->assertSessionHasErrors('blocks');
        $this->get(route('workouts.presentation', $id))->assertStatus(409);
        $this->get(route('workouts.preview', $id))->assertOk()->assertSee('Rascunho');
        $this->assertDatabaseHas('workouts', ['id' => $id, 'status' => 'draft']);
    }

    public function test_complete_draft_can_be_published_and_returned_to_draft(): void
    {
        $user = User::factory()->create();
        $workout = $this->createWorkoutFor($user, $this->workoutInput(status: 'draft'));
        $this->actingAs($user)->post(route('workouts.publish', $workout))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('workouts', ['id' => $workout->id, 'status' => 'published']);
        $this->get(route('workouts.presentation', $workout))->assertOk()->assertSee('SEM EXECUÇÃO DE CRONÔMETROS');
        $this->post(route('workouts.draft', $workout))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('workouts', ['id' => $workout->id, 'status' => 'draft']);
    }

    public function test_edit_replaces_blocks_and_stations_atomically_and_preserves_author(): void
    {
        $user = User::factory()->create();
        $data = $this->workoutInput('hyrox');
        $data['blocks'][0]['stations'] = [['name' => 'First'], ['name' => 'Second']];
        $workout = $this->createWorkoutFor($user, $data);
        $data['blocks'][0]['stations'] = [['name' => 'Second'], ['name' => 'First'], ['name' => 'Third']];
        $data['title'] = 'Edited workout';
        $this->actingAs($user)->put(route('workouts.update', $workout), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('workout_blocks', 1);
        $this->assertDatabaseCount('workout_stations', 3);
        $this->assertDatabaseHas('workout_stations', ['position' => 0, 'name' => 'Second']);
        $this->assertDatabaseHas('workouts', ['id' => $workout->id, 'title' => 'Edited workout', 'created_by' => $user->id]);
    }

    public function test_invalid_edit_preserves_original_content(): void
    {
        $user = User::factory()->create();
        $workout = $this->createWorkoutFor($user);
        $data = $this->workoutInput();
        $data['blocks'][0]['timer_type'] = 'amrap';
        unset($data['blocks'][0]['duration_seconds']);
        $this->actingAs($user)->put(route('workouts.update', $workout), $data)->assertSessionHasErrors('blocks.0.duration_seconds');
        $this->assertDatabaseHas('workouts', ['id' => $workout->id, 'title' => $workout->title]);
        $this->assertDatabaseHas('workout_blocks', ['workout_id' => $workout->id, 'timer_type' => 'for_time']);
    }

    public function test_deletion_requires_confirmation_and_removes_owned_children(): void
    {
        $user = User::factory()->create();
        $data = $this->workoutInput('hyrox');
        $data['blocks'][0]['stations'] = [['name' => 'Running']];
        $workout = $this->createWorkoutFor($user, $data);
        $this->actingAs($user)->delete(route('workouts.destroy', $workout))->assertSessionHasErrors('confirmation');
        $this->assertDatabaseCount('workouts', 1);
        $this->delete(route('workouts.destroy', $workout), ['confirmation' => 1])->assertRedirect();
        $this->assertDatabaseCount('workouts', 0);
        $this->assertDatabaseCount('workout_blocks', 0);
        $this->assertDatabaseCount('workout_stations', 0);
    }

    public static function invalidTimerCases(): array
    {
        return [
            'amrap duration' => ['crossfit', 'amrap', null, [], 'duration_seconds'],
            'emom interval' => ['crossfit', 'emom', 600, ['interval_seconds' => 'invalid'], 'timer_settings.interval_seconds'],
            'emom divisible' => ['crossfit', 'emom', 601, ['interval_seconds' => 60], 'timer_settings.interval_seconds'],
            'zero interval' => ['crossfit', 'emom', 600, ['interval_seconds' => 0], 'timer_settings.interval_seconds'],
            'tabata cycles' => ['functional', 'tabata', null, ['work_seconds' => 20, 'rest_seconds' => 10], 'timer_settings.rounds'],
            'interval work' => ['hyrox', 'interval', null, ['rest_seconds' => 20, 'rounds' => 4], 'timer_settings.work_seconds'],
            'negative rest' => ['functional', 'tabata', null, ['work_seconds' => 20, 'rest_seconds' => -1, 'rounds' => 4], 'timer_settings.rest_seconds'],
            'none duration' => ['functional', 'none', 90, [], 'duration_seconds'],
            'unknown format' => ['crossfit', 'unknown', null, ['work_seconds' => 20, 'rest_seconds' => 10, 'rounds' => 8], 'timer_type'],
            'circuit stations' => ['functional', 'circuit', null, ['work_seconds' => 30, 'rest_seconds' => 10, 'rounds' => 3], 'stations'],
        ];
    }

    #[DataProvider('invalidTimerCases')]
    public function test_publishing_validates_dynamic_timer_parameters(string $modality, string $type, ?int $duration, array $settings, string $error): void
    {
        $data = $this->workoutInput($modality);
        $data['blocks'][0]['timer_type'] = $type;
        $data['blocks'][0]['duration_seconds'] = $duration;
        $data['blocks'][0]['timer_settings'] = $settings;
        $this->actingAs(User::factory()->create())->post(route('workouts.store'), $data)->assertSessionHasErrors('blocks.0.'.$error);
        $this->assertDatabaseCount('workouts', 0);
    }

    public function test_draft_amrap_can_omit_duration_but_negative_values_are_always_rejected(): void
    {
        $data = $this->workoutInput(status: 'draft');
        $data['blocks'][0]['timer_type'] = 'amrap';
        unset($data['blocks'][0]['duration_seconds']);
        $this->actingAs(User::factory()->create())->post(route('workouts.store'), $data)->assertSessionHasNoErrors();
        $data['blocks'][0]['duration_seconds'] = -1;
        $this->post(route('workouts.store'), $data)->assertSessionHasErrors('blocks.0.duration_seconds');
        $this->assertDatabaseCount('workouts', 1);
    }

    public function test_forms_and_preview_render_multiline_text_safely_and_preserve_failed_input(): void
    {
        $user = User::factory()->create();
        $data = $this->workoutInput('hyrox', 'full');
        $data['blocks'][2]['description'] = "<script>alert(1)</script>\nSecond line";
        $workout = $this->createWorkoutFor($user, $data);
        $this->actingAs($user)->get(route('workouts.preview', $workout))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)->assertSee('Second line');
        $this->get(route('workouts.edit', $workout))->assertOk()->assertSee('Engine day');
        $data['blocks'][2]['duration_seconds'] = -10;
        $this->from(route('workouts.edit', $workout))->put(route('workouts.update', $workout), $data)->assertRedirect(route('workouts.edit', $workout))->assertSessionHasInput('blocks.2.description', $data['blocks'][2]['description']);
        $this->get(route('workouts.edit', $workout))->assertOk()->assertSee('Second line');
    }

    public function test_modality_pages_and_all_six_form_variants_render(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['crossfit', 'hyrox', 'functional'] as $slug) {
            $this->get(route('modalities.show', $slug))->assertOk()->assertSee('Cadastrar treino completo');
            foreach (['main', 'full'] as $structure) {
                $this->get(route('workouts.create', ['modality' => $slug, 'structure' => $structure]))->assertOk()->assertSee('Salvar e pré-visualizar');
            }
        }
    }

    public static function validTimerCases(): array
    {
        return [
            'for time without cap' => ['crossfit', 'for_time', null, []],
            'emom' => ['crossfit', 'emom', 600, ['interval_seconds' => 60]],
            'hyrox interval' => ['hyrox', 'interval', null, ['work_seconds' => 60, 'rest_seconds' => 30, 'rounds' => 5, 'transition_seconds' => 10]],
            'tabata' => ['functional', 'tabata', null, ['work_seconds' => 20, 'rest_seconds' => 10, 'rounds' => 8]],
            'repetitions' => ['functional', 'repetitions', null, ['rounds' => 4]],
            'untimed' => ['functional', 'none', null, []],
        ];
    }

    #[DataProvider('validTimerCases')]
    public function test_supported_formats_are_saved_with_their_canonical_configuration(string $modality, string $type, ?int $duration, array $settings): void
    {
        $data = $this->workoutInput($modality);
        $data['blocks'][0]['timer_type'] = $type;
        $data['blocks'][0]['duration_seconds'] = $duration;
        $data['blocks'][0]['timer_settings'] = $settings;
        $this->actingAs(User::factory()->create())->post(route('workouts.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('workout_blocks', ['timer_type' => $type, 'duration_seconds' => $duration, 'timer_settings->version' => 1]);
    }

    public function test_functional_full_workout_can_be_published(): void
    {
        $this->actingAs(User::factory()->create())->post(route('workouts.store'), $this->workoutInput('functional', 'full'))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('workout_blocks', 3);
        $this->assertDatabaseHas('workouts', ['structure' => 'full', 'status' => 'published']);
    }

    public function test_malformed_old_input_does_not_break_the_editor(): void
    {
        $data = $this->workoutInput('hyrox');
        $data['title'] = ['unexpected'];
        $data['blocks'][0]['title'] = ['unexpected'];
        $data['blocks'][0]['timer_settings'] = 'invalid';
        $this->actingAs(User::factory()->create())->from(route('workouts.create', ['modality' => 'hyrox']))->post(route('workouts.store'), $data)->assertSessionHasErrors(['title', 'blocks.0.title', 'blocks.0.timer_settings']);
        $this->get(route('workouts.create', ['modality' => 'hyrox']))->assertOk()->assertSee('10 burpees');
    }

    public function test_more_than_sixty_four_stations_are_rejected(): void
    {
        $data = $this->workoutInput('hyrox');
        $data['blocks'][0]['stations'] = array_fill(0, 65, ['name' => 'Station']);
        $this->actingAs(User::factory()->create())->post(route('workouts.store'), $data)->assertSessionHasErrors('blocks.0.stations');
        $this->assertDatabaseCount('workouts', 0);
    }
}
