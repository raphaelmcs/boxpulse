<?php

namespace Tests\Feature;

use App\Models\User;
use App\Workouts\TimerConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithSessions;
use Tests\TestCase;

class WorkoutEditorV2Test extends TestCase
{
    use InteractsWithSessions, RefreshDatabase;

    private function change(array $input, int $version, string $mode = 'draft', ?string $writeId = null): array
    {
        return [...$input, 'editor_mode' => $mode, 'expected_edit_version' => $version, 'editor_write_id' => $writeId ?? (string) Str::uuid()];
    }

    public function test_first_autosave_is_an_idempotent_draft_and_manual_save_advances_its_version(): void
    {
        $this->actingAs(User::factory()->create());
        $input = $this->workoutInput();
        $payload = $this->change($input, 0);
        $created = $this->postJson(route('workouts.store'), $payload)->assertOk()->assertJsonPath('version', 1)->assertJsonPath('published', false)->json();
        $this->postJson(route('workouts.store'), $payload)->assertOk()->assertExactJson($created);
        $this->postJson(route('workouts.store'), [...$payload, 'title' => 'Mesmo UUID com conteúdo diferente'])->assertConflict();
        $this->assertDatabaseCount('workouts', 1);
        $this->get($created['edit_url'])->assertOk()->assertSee('Salvar e pré-visualizar')->assertSee('data-editor-version="1"', false);

        $this->putJson($created['update_url'], $this->change([...$input, 'title' => 'Alteração manual'], 1))->assertOk()->assertJsonPath('version', 2);
        $this->assertDatabaseHas('workouts', ['title' => 'Alteração manual', 'edit_version' => 2, 'status' => 'draft']);
    }

    public function test_default_editor_offers_all_stages_and_legacy_main_workouts_can_switch_to_full(): void
    {
        $user = User::factory()->create();
        $input = $this->workoutInput(status: 'draft');
        $workout = $this->createWorkoutFor($user, $input);

        $this->actingAs($user)->get(route('workouts.create'))->assertOk()
            ->assertSee('name="structure" value="full"', false)
            ->assertSee('+ Adicionar Aquecimento / Warm-up')->assertSee('+ Adicionar Skill / Técnica')->assertSee('+ Adicionar WOD');
        $simplified = $this->get(route('workouts.create', ['structure' => 'main']))->assertOk()
            ->assertSee('name="structure" value="main"', false);
        $this->assertMatchesRegularExpression('/data-workout-stage-controls\s+hidden/', $simplified->getContent());
        $url = route('workouts.update', $workout);
        $this->get(route('workouts.edit', $workout))->assertOk()->assertSee('Adicionar outras etapas');
        $full = $this->workoutInput(structure: 'full', status: 'draft');
        $input['structure'] = 'full';
        $input['blocks'] = [$full['blocks'][0], $input['blocks'][0]];
        $this->putJson($url, $this->change($input, 1))->assertOk();
        $this->assertDatabaseHas('workouts', ['id' => $workout->id, 'structure' => 'full']);
        $this->assertDatabaseHas('workout_blocks', ['workout_id' => $workout->id, 'type' => 'warmup', 'position' => 0]);
        $this->assertDatabaseHas('workout_blocks', ['workout_id' => $workout->id, 'type' => 'workout', 'position' => 1]);
    }

    public function test_autosave_keeps_unfilled_stage_settings_for_reopening_and_excludes_them_from_presentation(): void
    {
        $this->actingAs(User::factory()->create());
        $input = $this->workoutInput(structure: 'full', status: 'draft');
        $placeholder = ['type' => 'skill', 'timer_type' => 'emom', 'duration_seconds' => 120];
        $input['blocks'] = [$input['blocks'][0], $placeholder];
        $saved = $this->postJson(route('workouts.store'), $this->change($input, 0))->assertOk()->assertJsonPath('has_draft', false)
            ->assertJsonPath('schedule_url', route('schedule', ['date' => $input['scheduled_for']]))->json();
        $this->assertDatabaseCount('workout_blocks', 1);
        $form = $this->get($saved['edit_url'])->assertOk()->viewData('form');
        $this->assertSame('skill', $form['blocks'][1]['type']);
        $this->assertSame(120, $form['blocks'][1]['duration_seconds']);
        $this->assertSame(['warmup'], $this->get($saved['preview_url'])->assertOk()->viewData('workout')->blocks->pluck('type')->all());

        $input['blocks'] = [...$input['blocks'], ['type' => 'workout', 'timer_type' => 'amrap']];
        $this->putJson($saved['update_url'], $this->change($input, 1))->assertOk();
        $input['blocks'] = [$input['blocks'][0], $input['blocks'][2]];
        $this->putJson($saved['update_url'], $this->change($input, 2))->assertOk();
        $form = $this->get($saved['edit_url'])->assertOk()->viewData('form');
        $this->assertSame(['warmup', 'workout'], array_column($form['blocks'], 'type'));

        $this->putJson($saved['update_url'], $this->change($input, 3, 'publish'))->assertOk()->assertJsonPath('published', true);
        $workout = $this->get($saved['preview_url'])->assertOk()->viewData('workout');
        $this->assertNull($workout->editor_draft);
        $this->post(route('coach.prepare', $workout))->assertSessionHasNoErrors();
        $session = $this->get(route('sessions.index'))->viewData('sessions')->sole();
        $this->getJson(route('sessions.state', $session))->assertOk()->assertJsonCount(1, 'data.timers')
            ->assertJsonPath('data.timers.0.block_type', 'warmup');
    }

    public function test_legacy_navigation_and_status_actions_on_stale_revisions_cannot_overwrite(): void
    {
        $user = User::factory()->create();
        $input = $this->workoutInput();
        $workout = $this->createWorkoutFor($user, $input);
        $this->actingAs($user);
        $url = route('workouts.update', $workout);
        $first = $this->change([...$input, 'title' => 'Revisão da primeira aba'], 1);
        $this->putJson($url, $first)->assertOk()->assertJsonPath('version', 2);
        $this->putJson($url, $first)->assertOk()->assertJsonPath('version', 2);
        $this->putJson($url, $this->change([...$input, 'title' => 'Segunda aba'], 1))->assertConflict();
        $this->postJson(route('workouts.publish', $workout), ['expected_edit_version' => 1])->assertConflict();
        $this->postJson(route('workouts.draft', $workout), ['expected_edit_version' => 1])->assertConflict();
        $this->get(route('workouts.preview', ['workout' => $workout, 'draft' => 1]))->assertOk()->assertSee('Revisão da primeira aba')->assertDontSee('Segunda aba');
        $this->get(route('workouts.presentation', ['workout' => $workout, 'draft' => 1]))->assertOk()->assertDontSee('Revisão da primeira aba');
        $this->actingAs(User::factory()->create())->putJson($url, $this->change($input, 2))->assertNotFound();
        $this->get(route('workouts.preview', ['workout' => $workout, 'draft' => 1]))->assertNotFound();
    }

    public function test_published_workout_keeps_programming_and_running_snapshot_until_explicit_republication(): void
    {
        $user = User::factory()->create();
        $original = [...$this->workoutInput(), 'title' => 'Aula original'];
        [$workout, $state] = $this->sessionFor($user, $original);
        $state = $this->command($state, 'start')->assertOk()->json('data');
        $draft = [...$original, 'title' => 'Aula revisada', 'scheduled_for' => '2026-11-11', 'presentation_options' => ['preparation_seconds' => 5, 'auto_advance' => true]];
        $draft['blocks'][0]['description'] = "\nSérie:\n10 reps\n20 bike";
        $draft['blocks'][0]['text_style'] = [
            'title' => ['text_color' => '#facc15', 'font_weight' => 'normal'],
            'description' => [1 => ['font_weight' => 'bold', 'display_role' => 'subtitle'], 3 => ['text_color' => '#4ade80']],
        ];
        $draft['blocks'][0]['load_entries'] = [['equipment' => 'Dumbbell', 'movement' => 'Dumbbell Snatch', 'rx' => '22,5 / 15 kg', 'source' => 'explicit', 'unit' => 'kg']];
        $saved = $this->putJson(route('workouts.update', $workout), $this->change($draft, 1))
            ->assertOk()->assertJsonPath('has_draft', true)->assertJsonPath('published', true)->assertJsonPath('version', 2)->json();
        $this->assertDatabaseHas('workouts', ['id' => $workout->id, 'title' => 'Aula original', 'scheduled_for' => '2026-11-10', 'status' => 'published', 'content_version' => 1]);
        $this->get($saved['preview_url'])->assertOk()->assertSee('Aula revisada')->assertSee('font-weight:400;color:#facc15;', false)
            ->assertSee('font-weight:700;">Série:', false)->assertSee('color:#4ade80;">20 bike', false)->assertSeeText('22,5 / 15 kg');
        $this->get(route('workouts.preview', $workout))->assertOk()->assertSee('Aula original')->assertDontSee('22,5 / 15 kg');
        $this->getJson(route('sessions.state', $state['id']))->assertOk()
            ->assertJsonPath('data.source.updated', false)->assertJsonPath('data.timers.0.text_style', null)->assertJsonPath('data.timers.0.load_entries', null);

        $this->post(route('workouts.publish', $workout), ['expected_edit_version' => 2])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('workouts', ['id' => $workout->id, 'title' => 'Aula revisada', 'scheduled_for' => '2026-11-11', 'content_version' => 2, 'edit_version' => 3, 'editor_draft' => null]);
        $this->getJson(route('sessions.state', $state['id']))->assertOk()
            ->assertJsonPath('data.source.updated', true)->assertJsonPath('data.timers.0.text_style', null);
        $this->get(route('sessions.tv', $state['id']))->assertOk()->assertDontSee('22,5 / 15 kg');

        $state = $this->command($this->completeLastStage($state), 'finish')->assertOk()->json('data');
        $this->post(route('coach.prepare', $workout), ['new_session_confirmation' => 1, 'previous_session_id' => $state['id']])->assertSessionHasNoErrors();
        $session = $this->get(route('sessions.index'))->viewData('sessions')->first();
        $this->getJson(route('sessions.state', $session))->assertOk()
            ->assertJsonPath('data.auto_advance', true)
            ->assertJsonPath('data.timers.0.config.preparation_ms', 5000)
            ->assertJsonPath('data.timers.0.description', "\nSérie:\n10 reps\n20 bike")
            ->assertJsonPath('data.timers.0.text_style.description.1.font_weight', 'bold')
            ->assertJsonPath('data.timers.0.text_style.description.3.text_color', '#4ADE80')
            ->assertJsonPath('data.timers.0.load_entries.0.source', 'explicit');
        $this->get(route('sessions.tv', $session))->assertOk()->assertSeeText('22,5 / 15 kg')->assertSee('font-weight:400;color:#facc15;', false);
        $this->get(route('coach.ready', $session))->assertOk()->assertSee('font-weight:700;">Série:', false);
    }

    public function test_editor_and_ready_render_the_shared_tv_screen_without_session_controls(): void
    {
        [$workout, $state] = $this->sessionFor(User::factory()->create());
        foreach ([
            route('workouts.create'), route('workouts.edit', $workout), route('workouts.preview', $workout), route('coach.ready', $state['id']),
        ] as $url) {
            $this->get($url)->assertOk()->assertSee('Visualizar na TV')->assertSee('<dialog class="presentation-check"', false)
                ->assertSee('data-preview-screen', false)->assertDontSee('data-capacity-resolution', false)
                ->assertSee('data-preview-fullscreen', false)->assertSee('data-content-pages', false)
                ->assertDontSee('data-session-app', false)->assertDontSee('data-session-command', false);
        }
        $this->get(route('workouts.edit', $workout))->assertOk()
            ->assertDontSee('Opções de apresentação')->assertDontSee('name="presentation_options[', false)
            ->assertDontSee('name="auto_advance"', false)->assertDontSee('name="preparation_seconds"', false);
        $this->get(route('sessions.tv', $state['id']))->assertOk()->assertSee('data-session-app', false)
            ->assertSee('data-content-pages', false)->assertDontSee('data-preview-screen', false);
        $this->getJson(route('sessions.state', $state['id']))->assertOk()
            ->assertJsonPath('data.version', $state['version'])->assertJsonPath('data.timers.0.state', 'idle');
        $this->assertDatabaseCount('workout_sessions', 1);
    }

    public function test_autosave_without_operational_fields_preserves_legacy_preferences_on_republication(): void
    {
        $user = User::factory()->create();
        $original = $this->workoutInput();
        $workout = $this->createWorkoutFor($user, [...$original, 'presentation_options' => ['preparation_seconds' => '20', 'auto_advance' => '1']]);

        $this->actingAs($user)->putJson(route('workouts.update', $workout), $this->change([...$original, 'title' => 'Revisão do treino'], 1))
            ->assertOk()->assertJsonPath('has_draft', true);
        $this->post(route('workouts.publish', $workout), ['expected_edit_version' => 2])->assertSessionHasNoErrors()->assertRedirect();
        $this->get(route('workouts.presentation', $workout))->assertOk()
            ->assertSee('value="20" selected', false)->assertSee('name="auto_advance" value="1" checked', false);
        $this->post(route('coach.prepare', $workout), ['preparation_seconds' => '', 'auto_advance' => '0'])->assertSessionHasNoErrors();
        $session = $this->get(route('sessions.index'))->viewData('sessions')->sole();
        $this->assertNull($session->preparation_override);
        $this->getJson(route('sessions.state', $session))->assertOk()->assertJsonPath('data.auto_advance', false)
            ->assertJsonPath('data.timers.0.config.preparation_ms', 10000);
    }

    public function test_invalid_styles_are_rejected_and_line_and_title_styles_can_be_independently_reset(): void
    {
        $user = User::factory()->create();
        $input = $this->workoutInput(status: 'draft');
        $workout = $this->createWorkoutFor($user, $input);
        $this->actingAs($user);
        $url = route('workouts.update', $workout);
        $input['blocks'][0]['text_style'] = ['title' => ['text_color' => 'red;display:none']];
        $this->putJson($url, $this->change($input, 1))->assertUnprocessable()->assertJsonValidationErrors('blocks.0.text_style.title.text_color');
        $input['blocks'][0]['text_style'] = ['description' => [1 => ['font_weight' => 'bold', 'text_color' => '#ffffff']], 'title' => ['font_weight' => 'normal']];
        $this->putJson($url, $this->change($input, 1))->assertOk()->assertJsonPath('version', 2);
        $this->get(route('workouts.preview', $workout))->assertOk()->assertSee('font-weight:700;color:#ffffff;">200 m corrida', false);
        $input['blocks'][0]['text_style'] = ['title' => ['font_weight' => 'normal']];
        $this->putJson($url, $this->change($input, 2))->assertOk();
        $this->get(route('workouts.preview', $workout))->assertOk()->assertSee('font-weight:400;', false)->assertDontSee('font-weight:700;', false);
        $input['blocks'][0]['text_style'] = null;
        $this->putJson($url, $this->change($input, 3))->assertOk();
        $this->assertDatabaseHas('workout_blocks', ['workout_id' => $workout->id, 'text_style' => null]);
    }

    public function test_station_style_is_preserved_by_snapshot_and_tracks_station_order(): void
    {
        $this->actingAs(User::factory()->create());
        $input = $this->workoutInput('functional');
        $input['blocks'][0]['timer_type'] = 'circuit';
        $input['blocks'][0]['duration_seconds'] = null;
        $input['blocks'][0]['timer_settings'] = ['rounds' => 1, 'work_seconds' => 20, 'rest_seconds' => 5];
        $station = ['name' => 'Remo', 'description' => "Técnica:\nRespire", 'text_style' => [
            'name' => ['font_weight' => 'normal'],
            'description' => [1 => ['text_color' => '#60A5FA']],
        ]];
        $input['blocks'][0]['stations'] = [$station, ['name' => 'Bike']];
        $saved = $this->postJson(route('workouts.store'), $this->change($input, 0))->assertOk()->json();
        $input['blocks'][0]['stations'] = array_reverse($input['blocks'][0]['stations']);
        $published = $this->putJson($saved['update_url'], $this->change($input, 1, 'publish'))->assertOk()->json();
        $workout = $this->get($published['preview_url'])->assertOk()->assertSee('color:#60a5fa;">Respire', false)->viewData('workout');
        $this->post(route('coach.prepare', $workout))->assertSessionHasNoErrors();
        $session = $this->get(route('sessions.index'))->viewData('sessions')->first();
        $this->getJson(route('sessions.state', $session))->assertOk()->assertJsonPath('data.timers.0.stations.0.name', 'Bike')
            ->assertJsonPath('data.timers.0.stations.1.text_style.name.font_weight', 'normal')
            ->assertJsonPath('data.timers.0.stations.1.text_style.description.1.text_color', '#60A5FA');
    }

    public function test_equipment_without_values_is_saved_as_draft_and_does_not_publish_an_empty_stage(): void
    {
        $this->actingAs(User::factory()->create());
        $input = $this->workoutInput();
        $input['blocks'] = [['type' => 'workout', 'timer_type' => 'none', 'load_entries' => [['equipment' => 'Kettlebell']]]];
        $saved = $this->postJson(route('workouts.store'), $this->change($input, 0))->assertOk()->json();
        $this->get($saved['edit_url'])->assertOk()->assertSee('Kettlebell');
        $this->putJson($saved['update_url'], $this->change($input, 1, 'publish'))
            ->assertUnprocessable()->assertJsonValidationErrors('blocks');
    }

    public static function formats(): array
    {
        return array_map(fn ($type) => [$type], array_keys(TimerConfiguration::LABELS));
    }

    #[DataProvider('formats')]
    public function test_editor_saves_and_publishes_every_timer_format_with_an_optional_stage(string $type): void
    {
        $this->actingAs(User::factory()->create());
        $input = $this->workoutInput(structure: 'full');
        $input['blocks'] = [[
            'type' => 'warmup', 'title' => 'Instruções', 'description' => 'Treino de teste', 'timer_type' => $type,
            'timer_settings' => array_intersect_key(['work_seconds' => 20, 'rest_seconds' => 10, 'rounds' => 3], array_flip(TimerConfiguration::settingKeys($type))),
            'stations' => $type === 'circuit' ? [['name' => 'Remo']] : [],
        ]];
        if (TimerConfiguration::registry()[$type]['duration_allowed']) {
            $input['blocks'][0]['duration_minutes'] = 2;
        }
        $saved = $this->postJson(route('workouts.store'), $this->change($input, 0))->assertOk()->json();
        $this->get($saved['edit_url'])->assertOk()->assertSee('data-stage="warmup"', false);
        $this->putJson($saved['update_url'], $this->change($input, 1, 'publish'))->assertOk()->assertJsonPath('published', true);
        $this->assertDatabaseCount('workout_blocks', 1);
        $this->assertDatabaseHas('workout_blocks', ['type' => 'warmup', 'timer_type' => $type]);
    }
}
