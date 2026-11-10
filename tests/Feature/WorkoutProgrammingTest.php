<?php

namespace Tests\Feature;

use App\Actions\CopyWorkoutWeek;
use App\Models\User;
use App\Models\Workout;
use App\Tenancy\CurrentBox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\InteractsWithWorkouts;
use Tests\TestCase;

class WorkoutProgrammingTest extends TestCase
{
    use InteractsWithWorkouts, RefreshDatabase;

    public function test_week_starts_on_monday_and_lists_multiple_workouts_per_day_grouped_by_modality(): void
    {
        $user = User::factory()->create();
        $first = $this->createWorkoutFor($user, [...$this->workoutInput(), 'title' => 'CrossFit one']);
        $this->createWorkoutFor($user, [...$this->workoutInput(), 'title' => 'CrossFit two']);
        $this->createWorkoutFor($user, [...$this->workoutInput('hyrox'), 'title' => 'Hyrox one']);
        $this->actingAs($user)->get(route('schedule', ['date' => '2026-11-11']))->assertOk()->assertSee('09/11')->assertSee('15/11/2026')->assertSeeInOrder(['segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado', 'domingo'])->assertSee('CrossFit one')->assertSee('CrossFit two')->assertSee('Hyrox one')->assertSee(route('workouts.show', $first), false);
    }

    public function test_modality_filter_and_previous_future_week_navigation(): void
    {
        $user = User::factory()->create();
        $this->createWorkoutFor($user, [...$this->workoutInput(), 'title' => 'Only crossfit']);
        $this->createWorkoutFor($user, [...$this->workoutInput('hyrox'), 'title' => 'Only hyrox']);
        $this->actingAs($user)->get(route('schedule', ['date' => '2026-11-10', 'modality' => 'hyrox']))->assertOk()->assertSee('Only hyrox')->assertDontSee('Only crossfit')->assertSee('2026-11-02')->assertSee('2026-11-16');
        $this->get(route('schedule', ['date' => '2026-11-18']))->assertOk()->assertDontSee('Only hyrox');
    }

    public function test_history_uses_box_timezone_filters_modality_and_excludes_future(): void
    {
        $this->travelTo(Carbon::parse('2026-11-11 01:00:00', 'UTC'));
        $user = User::factory()->create();
        $this->createWorkoutFor($user, [...$this->workoutInput(), 'title' => 'Previous CrossFit', 'scheduled_for' => '2026-11-09']);
        $this->createWorkoutFor($user, [...$this->workoutInput('hyrox'), 'title' => 'Previous HYROX', 'scheduled_for' => '2026-11-09']);
        $this->createWorkoutFor($user, [...$this->workoutInput(), 'title' => 'Current workout']);
        $this->actingAs($user)->get(route('workouts.index', ['modality' => 'crossfit']))->assertOk()->assertSee('Previous CrossFit')->assertDontSee('Previous HYROX')->assertDontSee('Current workout');
        $this->get(route('schedule'))->assertOk()->assertViewHas('selected', fn ($date) => $date->toDateString() === '2026-11-10');
    }

    public function test_duplicate_is_a_deep_independent_draft_and_does_not_overwrite_existing_workouts(): void
    {
        $user = User::factory()->create();
        $data = $this->workoutInput('hyrox', 'full');
        $data['blocks'][2]['stations'] = [['name' => 'SkiErg', 'distance' => 1000]];
        $source = $this->createWorkoutFor($user, $data);
        $this->actingAs($user)->post(route('workouts.copy', $source), ['scheduled_for' => '2026-11-10'])->assertRedirect();
        $this->assertDatabaseCount('workouts', 2);
        $this->assertDatabaseCount('workout_blocks', 6);
        $this->assertDatabaseCount('workout_stations', 2);
        app(CurrentBox::class)->set($user->box_id);
        $copy = Workout::where('id', '!=', $source->id)->with('blocks.stations')->firstOrFail();
        $this->assertSame('draft', $copy->status);
        $this->assertNotSame($source->blocks[2]->id, $copy->blocks[2]->id);
        $copy->blocks[2]->stations[0]->update(['name' => 'Changed station']);
        $this->assertDatabaseHas('workout_stations', ['workout_block_id' => $source->blocks[2]->id, 'name' => 'SkiErg']);
        app(CurrentBox::class)->clear();
    }

    public function test_week_copy_keeps_weekday_multiple_workouts_and_all_modalities_as_drafts(): void
    {
        $user = User::factory()->create();
        $this->createWorkoutFor($user, [...$this->workoutInput('crossfit', 'full'), 'scheduled_for' => '2026-11-09']);
        $this->createWorkoutFor($user, $this->workoutInput('hyrox'));
        $this->createWorkoutFor($user, $this->workoutInput('functional'));
        $this->actingAs($user)->post(route('schedule.copy'), ['source_date' => '2026-11-10', 'target_date' => '2026-11-18', 'conflict_policy' => 'abort', 'confirmation' => 1])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('workouts', 6);
        $this->assertDatabaseHas('workouts', ['scheduled_for' => '2026-11-16', 'status' => 'draft', 'created_by' => $user->id]);
        $this->assertDatabaseHas('workouts', ['scheduled_for' => '2026-11-17', 'status' => 'draft']);
        $this->assertDatabaseCount('workout_blocks', 10);
    }

    public function test_week_conflict_aborts_atomically_and_append_preserves_existing_workouts(): void
    {
        $user = User::factory()->create();
        $source = $this->createWorkoutFor($user);
        $existing = $this->createWorkoutFor($user, [...$this->workoutInput(), 'title' => 'Keep me', 'scheduled_for' => '2026-11-17']);
        $data = ['source_date' => '2026-11-09', 'target_date' => '2026-11-16', 'conflict_policy' => 'abort', 'confirmation' => 1];
        $this->actingAs($user)->post(route('schedule.copy'), $data)->assertSessionHasErrors('target_date');
        $this->assertDatabaseCount('workouts', 2);
        $this->post(route('schedule.copy'), [...$data, 'conflict_policy' => 'append'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('workouts', 3);
        $this->assertDatabaseHas('workouts', ['id' => $existing->id, 'title' => 'Keep me', 'status' => 'published']);
        $this->assertDatabaseHas('workouts', ['id' => $source->id, 'status' => 'published']);
    }

    public function test_week_copy_requires_confirmation_explicit_conflict_policy_and_different_week(): void
    {
        $user = User::factory()->create();
        $this->createWorkoutFor($user);
        $data = ['source_date' => '2026-11-09', 'target_date' => '2026-11-16'];
        $this->actingAs($user)->post(route('schedule.copy'), $data)->assertSessionHasErrors(['confirmation', 'conflict_policy']);
        $this->post(route('schedule.copy'), [...$data, 'confirmation' => 1, 'conflict_policy' => 'abort', 'target_date' => '2026-11-11'])->assertSessionHasErrors('target_date');
        $this->assertDatabaseCount('workouts', 1);
    }

    public function test_empty_source_week_is_not_reported_as_successful_copy(): void
    {
        $this->actingAs(User::factory()->create())->post(route('schedule.copy'), ['source_date' => '2026-11-09', 'target_date' => '2026-11-16', 'conflict_policy' => 'abort', 'confirmation' => 1])->assertSessionHasErrors('source_date');
        $this->assertDatabaseCount('workouts', 0);
    }

    public function test_invalid_calendar_date_is_rejected_instead_of_causing_an_error(): void
    {
        $this->actingAs(User::factory()->create())->get(route('schedule', ['date' => '2026-99-40']))->assertSessionHasErrors('date');
    }

    public function test_failure_midway_through_week_copy_rolls_back_every_copy(): void
    {
        $user = User::factory()->create();
        $this->createWorkoutFor($user);
        $this->createWorkoutFor($user);
        $attempts = 0;
        Workout::creating(function () use (&$attempts): void {
            if (++$attempts === 2) {
                throw new \RuntimeException('Simulated copy failure');
            }
        });
        app(CurrentBox::class)->set($user->box_id);
        try {
            app(CopyWorkoutWeek::class)->handle($user, ['source_date' => '2026-11-09', 'target_date' => '2026-11-16', 'conflict_policy' => 'append']);
            $this->fail('Expected copy failure.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Simulated copy failure', $error->getMessage());
        }
        $this->assertDatabaseCount('workouts', 2);
        $this->assertDatabaseCount('workout_blocks', 2);
        app(CurrentBox::class)->clear();
    }
}
