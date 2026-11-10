<?php

namespace Tests\Feature;

use App\Actions\SaveWorkout;
use App\Models\Box;
use App\Models\User;
use App\Models\WorkoutBlock;
use App\Models\WorkoutStation;
use App\Tenancy\CurrentBox;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithWorkouts;
use Tests\TestCase;

class WorkoutSecurityTest extends TestCase
{
    use InteractsWithWorkouts, RefreshDatabase;

    public function test_every_mutating_and_preview_route_rejects_foreign_workout_ids(): void
    {
        $owner = User::factory()->create();
        $foreign = $this->createWorkoutFor(User::factory()->create());
        $this->actingAs($owner);
        foreach (['workouts.show', 'workouts.edit', 'workouts.preview', 'workouts.presentation'] as $route) {
            $this->get(route($route, $foreign))->assertNotFound();
        }
        $this->put(route('workouts.update', $foreign), $this->workoutInput())->assertNotFound();
        $this->delete(route('workouts.destroy', $foreign), ['confirmation' => 1])->assertNotFound();
        $this->post(route('workouts.publish', $foreign))->assertNotFound();
        $this->post(route('workouts.draft', $foreign))->assertNotFound();
        $this->post(route('workouts.copy', $foreign), ['scheduled_for' => '2026-11-12'])->assertNotFound();
        $this->assertDatabaseCount('workouts', 1);
        $this->assertDatabaseHas('workouts', ['id' => $foreign->id, 'status' => 'published']);
    }

    public function test_user_cannot_inject_box_or_author_but_valid_workout_is_assigned_to_current_user(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        $data = [...$this->workoutInput(), 'box_id' => $foreign->box_id, 'created_by' => $foreign->id];
        $this->actingAs($owner)->post(route('workouts.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('workouts', ['box_id' => $owner->box_id, 'created_by' => $owner->id]);
        $this->assertDatabaseMissing('workouts', ['box_id' => $foreign->box_id]);
    }

    public function test_nested_resource_ids_and_box_ids_are_not_accepted_from_forms(): void
    {
        $data = $this->workoutInput('hyrox');
        $data['blocks'][0]['box_id'] = 999;
        $data['blocks'][0]['stations'] = [['name' => 'SkiErg', 'id' => 1234]];
        $this->actingAs(User::factory()->create())->post(route('workouts.store'), $data)->assertSessionHasErrors(['blocks.0', 'blocks.0.stations.0']);
        $this->assertDatabaseCount('workouts', 0);
    }

    public function test_composite_foreign_key_prevents_a_block_from_linking_to_another_box(): void
    {
        $owner = User::factory()->create();
        $foreign = $this->createWorkoutFor(User::factory()->create());
        app(CurrentBox::class)->set($owner->box_id);
        $this->expectException(QueryException::class);
        WorkoutBlock::factory()->create(['workout_id' => $foreign->id]);
    }

    public function test_composite_foreign_key_prevents_a_station_from_linking_to_another_box(): void
    {
        $owner = User::factory()->create();
        $foreign = $this->createWorkoutFor(User::factory()->create());
        app(CurrentBox::class)->set($owner->box_id);
        $this->expectException(QueryException::class);
        WorkoutStation::factory()->create(['workout_block_id' => $foreign->blocks[0]->id]);
    }

    public function test_unscoped_children_are_not_exposed_without_tenant_context(): void
    {
        $user = User::factory()->create();
        $data = $this->workoutInput('hyrox');
        $data['blocks'][0]['stations'] = [['name' => 'Protected station']];
        $this->createWorkoutFor($user, $data);
        $this->assertSame(0, WorkoutBlock::count());
        $this->assertSame(0, WorkoutStation::count());
    }

    public function test_calendar_history_and_week_copy_are_isolated_from_other_boxes(): void
    {
        $this->travelTo(Carbon::parse('2026-11-11 12:00:00', 'UTC'));
        $owner = User::factory()->create();
        $this->createWorkoutFor($owner, [...$this->workoutInput(), 'title' => 'Owned content']);
        $foreign = $this->createWorkoutFor(User::factory()->create(), [...$this->workoutInput(), 'title' => 'Secret foreign content']);
        $this->actingAs($owner)->get(route('schedule', ['date' => '2026-11-10']))->assertOk()->assertSee('Owned content')->assertDontSee('Secret foreign content');
        $this->get(route('workouts.index'))->assertOk()->assertSee('Owned content')->assertDontSee('Secret foreign content');
        $this->post(route('schedule.copy'), ['source_date' => '2026-11-09', 'target_date' => '2026-11-16', 'conflict_policy' => 'append', 'confirmation' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('workouts', 3);
        $this->assertDatabaseMissing('workouts', ['box_id' => $foreign->box_id, 'scheduled_for' => '2026-11-17']);
    }

    public function test_unknown_role_cannot_create_or_list_workouts_and_coach_cannot_delete(): void
    {
        $unknown = User::factory()->create(['role' => 'unknown']);
        $this->actingAs($unknown)->post(route('workouts.store'), $this->workoutInput())->assertForbidden();
        $this->get(route('schedule'))->assertForbidden();
        $this->get(route('workouts.index'))->assertForbidden();
        $coach = User::factory()->create(['role' => 'coach']);
        $workout = $this->createWorkoutFor($coach);
        $this->actingAs($coach)->delete(route('workouts.destroy', $workout), ['confirmation' => 1])->assertForbidden();
        $this->assertDatabaseCount('workouts', 1);
    }

    public function test_box_logo_cannot_resolve_another_box_file_or_arbitrary_path(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $foreign = Box::factory()->create();
        $owner->box->update(['logo_path' => "boxes/{$owner->box_id}/logo.png"]);
        Storage::disk('public')->put("boxes/{$owner->box_id}/logo.png", base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jFJkAAAAASUVORK5CYII='));
        $this->actingAs($owner)->get(route('box.logo'))->assertOk();
        $owner->box->update(['logo_path' => "boxes/{$foreign->id}/logo.png"]);
        $this->get(route('box.logo'))->assertNotFound();
        $owner->box->update(['logo_path' => '../../.env']);
        $this->get(route('box.logo'))->assertNotFound();
    }

    public function test_failures_during_edit_roll_back_the_original_blocks_and_stations(): void
    {
        $user = User::factory()->create();
        $data = $this->workoutInput('hyrox');
        $data['blocks'][0]['stations'] = [['name' => 'Original station']];
        $source = $this->createWorkoutFor($user, $data);
        WorkoutStation::creating(function (): void {
            throw new \RuntimeException('Simulated database failure');
        });
        app(CurrentBox::class)->set($user->box_id);
        try {
            app(SaveWorkout::class)->handle($user, [...$data, 'title' => 'Do not persist'], $source);
            $this->fail('Expected write failure.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Simulated database failure', $error->getMessage());
        }
        $this->assertDatabaseHas('workouts', ['id' => $source->id, 'title' => $source->title]);
        $this->assertDatabaseHas('workout_stations', ['name' => 'Original station', 'workout_block_id' => $source->blocks[0]->id]);
        app(CurrentBox::class)->clear();
    }
}
