<?php

namespace Tests\Feature;

use App\Models\User;
use App\Workouts\SuggestedLoads;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithSessions;
use Tests\TestCase;

class SuggestedLoadsTest extends TestCase
{
    use InteractsWithSessions, RefreshDatabase;

    private function loads(array $overrides = []): array
    {
        return array_replace(['equipment' => 'DB', 'rx' => '22/16', 'intermediate' => '20/14', 'scale' => '18/12', 'unit' => null], $overrides);
    }

    private function inputWithLoads(?array $loads = null, string $modality = 'crossfit', string $structure = 'main'): array
    {
        $input = $this->workoutInput($modality, $structure);
        $input['blocks'][array_key_last($input['blocks'])]['suggested_loads'] = $loads;

        return $input;
    }

    public static function modalities(): array
    {
        return [['crossfit'], ['hyrox'], ['functional']];
    }

    #[DataProvider('modalities')]
    public function test_loads_persist_in_main_block_for_every_modality_and_render_in_details_and_preview(string $modality): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('workouts.store'), $this->inputWithLoads($this->loads(), $modality))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('workout_blocks', 1);
        $workout = $this->get(route('schedule', ['date' => '2026-11-10']))->assertOk()->viewData('workouts')->flatten()->first();
        foreach (['workouts.show', 'workouts.preview'] as $route) {
            $this->get(route($route, $workout))->assertOk()->assertSee('CARGAS SUGERIDAS')->assertSee('22/16')->assertSee('20/14')->assertSee('18/12')->assertSee('DB');
        }
        $this->get(route('workouts.edit', $workout))->assertOk()->assertSee('value="22/16"', false);
    }

    public function test_no_loads_are_required_to_publish_and_equipment_alone_is_not_displayed(): void
    {
        $user = User::factory()->create();
        $workout = $this->createWorkoutFor($user, $this->inputWithLoads(['equipment' => 'Barbell', 'unit' => 'kg']));
        $this->actingAs($user)->get(route('workouts.preview', $workout))->assertOk()->assertDontSee('CARGAS SUGERIDAS');
        $this->assertDatabaseHas('workout_blocks', ['workout_id' => $workout->id, 'suggested_loads' => null]);
        $this->post(route('workouts.store'), $this->workoutInput())->assertSessionHasNoErrors();
    }

    public function test_only_rx_is_rendered_and_zero_is_valid_without_assumed_conversion(): void
    {
        $user = User::factory()->create();
        $workout = $this->createWorkoutFor($user, $this->inputWithLoads(['rx' => '0', 'unit' => 'lb']));
        $this->actingAs($user)->get(route('workouts.preview', $workout))->assertOk()->assertSee('0 lb')->assertDontSee('Intermediate')->assertDontSee('Scale');
        $this->assertSame('60 kg', SuggestedLoads::display('60 kg', 'lb'));
        $this->assertSame('22/16', SuggestedLoads::display('22/16', 'kg'));
        $this->assertSame('40/30 kg', SuggestedLoads::display('40/30 kg', 'lb'));
        $this->assertSame('22.5 lb', SuggestedLoads::display('22.5', 'lb'));
        $this->assertSame('20', SuggestedLoads::display('20', null));
    }

    public function test_edit_remove_individual_levels_preserve_on_unrelated_edit_and_clear_all(): void
    {
        $user = User::factory()->create();
        $workout = $this->createWorkoutFor($user, $this->inputWithLoads($this->loads()));
        $this->actingAs($user)->put(route('workouts.update', $workout), $this->inputWithLoads($this->loads(['equipment' => 'KB', 'rx' => '24/16', 'intermediate' => '', 'scale' => null])))->assertSessionHasNoErrors();
        $this->get(route('workouts.show', $workout))->assertOk()->assertSee('24/16')->assertSee('KB')->assertDontSee('Intermediate')->assertDontSee('Scale');
        $this->put(route('workouts.update', $workout), [...$this->workoutInput(), 'title' => 'Updated title only'])->assertSessionHasNoErrors();
        $this->get(route('workouts.show', $workout))->assertOk()->assertSee('24/16')->assertSee('Updated title only');
        $this->put(route('workouts.update', $workout), $this->inputWithLoads(['equipment' => 'KB', 'rx' => '', 'intermediate' => '', 'scale' => '', 'unit' => 'kg']))->assertSessionHasNoErrors();
        $this->get(route('workouts.preview', $workout))->assertOk()->assertDontSee('CARGAS SUGERIDAS');
        $this->assertDatabaseHas('workout_blocks', ['workout_id' => $workout->id, 'suggested_loads' => null]);
    }

    public function test_duplicate_date_and_week_copy_preserve_loads_with_new_block_ids(): void
    {
        $user = User::factory()->create();
        $workout = $this->createWorkoutFor($user, $this->inputWithLoads($this->loads()));
        $this->actingAs($user)->post(route('workouts.copy', $workout), ['scheduled_for' => '2026-11-12'])->assertSessionHasNoErrors();
        $copies = $this->get(route('schedule', ['date' => '2026-11-10']))->viewData('workouts')->flatten();
        $copy = $copies->first(fn ($item) => $item->id !== $workout->id);
        $copy = $this->get(route('workouts.show', $copy))->assertOk()->assertSee('22/16')->assertSee('18/12')->viewData('workout');
        $this->assertNotSame($workout->blocks->first()->id, $copy->blocks->first()->id);
        $this->assertSame('draft', $copy->status);
        $this->post(route('schedule.copy'), ['source_date' => '2026-11-10', 'target_date' => '2026-11-17', 'conflict_policy' => 'append', 'confirmation' => 1])->assertSessionHasNoErrors();
        $week = $this->get(route('schedule', ['date' => '2026-11-17']))->viewData('workouts')->flatten();
        $this->assertCount(2, $week);
        foreach ($week as $copied) {
            $this->get(route('workouts.show', $copied))->assertOk()->assertSee('22/16')->assertSee('20/14')->assertSee('18/12');
        }
    }

    public function test_snapshot_loads_only_in_main_stage_and_started_session_keeps_original_after_source_edit(): void
    {
        $user = User::factory()->create();
        [$workout, $state] = $this->sessionFor($user, $this->inputWithLoads($this->loads(), 'crossfit', 'full'));
        $this->assertNull($state['timers'][0]['suggested_loads']);
        $this->assertNull($state['timers'][1]['suggested_loads']);
        $this->assertEquals($this->loads(), $state['timers'][2]['suggested_loads']);
        foreach (['sessions.control', 'sessions.tv'] as $route) {
            $this->get(route($route, $state['id']))->assertOk()->assertDontSee('22/16')->assertSee('data-suggested-loads', false);
        }
        $state = $this->command($state, 'next')->assertOk()->json('data');
        $state = $this->command($state, 'next')->assertOk()->json('data');
        foreach (['sessions.control', 'sessions.tv'] as $route) {
            $this->get(route($route, $state['id']))->assertOk()->assertSee('22/16')->assertSee('20/14')->assertSee('18/12');
        }
        $state = $this->command($state, 'start')->assertOk()->json('data');
        $updated = $this->inputWithLoads($this->loads(['rx' => '30/20']), 'crossfit', 'full');
        $this->put(route('workouts.update', $workout), [...$updated, 'status' => 'draft'])->assertSessionHasNoErrors();
        $this->post(route('workouts.publish', $workout))->assertSessionHasNoErrors();
        $this->get(route('workouts.preview', $workout))->assertOk()->assertSee('30/20');
        $this->post(route('sessions.store', $workout))->assertRedirect(route('sessions.control', $state['id']));
        $this->getJson(route('sessions.state', $state['id']))->assertOk()->assertJsonPath('data.timers.2.suggested_loads.rx', '22/16');
        $this->post(route('sessions.restart', $state['id']), ['client_id' => $state['controller_client_id'], 'expected_version' => $state['version'], 'expected_workout_version' => 2, 'confirmation' => 1])->assertSessionHasNoErrors();
        $new = $this->get(route('sessions.index'))->viewData('sessions')->first();
        $this->assertNotSame($state['id'], $new->id);
        $this->getJson(route('sessions.state', $new))->assertOk()->assertJsonPath('data.timers.2.suggested_loads.rx', '30/20');
        $this->getJson(route('sessions.state', $state['id']))->assertOk()->assertJsonPath('data.timers.2.suggested_loads.rx', '22/16');
    }

    public function test_pending_session_is_replaced_with_updated_loads_and_empty_following_stage_is_hidden(): void
    {
        [$workout, $old] = $this->sessionFor(User::factory()->create(), $this->inputWithLoads($this->loads()));
        $this->put(route('workouts.update', $workout), $this->inputWithLoads(['rx' => '60 kg']))->assertSessionHasNoErrors();
        $this->post(route('sessions.store', $workout))->assertSessionHasNoErrors();
        $new = $this->get(route('sessions.index'))->viewData('sessions')->first();
        $this->getJson(route('sessions.state', $new))->assertOk()->assertJsonPath('data.timers.0.suggested_loads.rx', '60 kg');
        $this->getJson(route('sessions.state', $old['id']))->assertOk()->assertJsonPath('data.timers.0.suggested_loads.rx', '22/16');
    }

    public function test_rejects_oversized_malformed_or_control_character_loads_and_loads_in_warmup(): void
    {
        $this->actingAs(User::factory()->create());
        foreach ([['rx' => str_repeat('x', 61)], ['equipment' => str_repeat('x', 81)], ['rx' => ['nested']], ['unit' => 'oz'], ['rx' => "20\n30"], ['block_id' => 99]] as $loads) {
            $this->post(route('workouts.store'), $this->inputWithLoads($loads))->assertSessionHasErrors();
        }
        $data = $this->inputWithLoads($this->loads(), 'crossfit', 'full');
        $data['blocks'][0]['suggested_loads'] = ['rx' => '100 kg'];
        $this->post(route('workouts.store'), $data)->assertSessionHasErrors('blocks.0.suggested_loads');
        $this->assertDatabaseCount('workouts', 0);
        $this->from(route('workouts.create'))->post(route('workouts.store'), $this->inputWithLoads(['rx' => ['nested']]))->assertSessionHasErrors();
        $this->get(route('workouts.create'))->assertOk();
    }

    public function test_load_text_is_escaped_and_other_box_cannot_read_or_update_it(): void
    {
        $owner = User::factory()->create();
        $workout = $this->createWorkoutFor($owner, $this->inputWithLoads(['rx' => '<script>alert(1)</script>']));
        $this->actingAs($owner)->get(route('workouts.preview', $workout))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->actingAs(User::factory()->create())->get(route('workouts.show', $workout))->assertNotFound();
        $this->put(route('workouts.update', $workout), $this->inputWithLoads(['rx' => '99']))->assertNotFound();
    }
}
