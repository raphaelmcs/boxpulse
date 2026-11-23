<?php

namespace Tests\Feature;

use App\Actions\CopyWorkoutWeek;
use App\Models\Box;
use App\Models\TrainingModality;
use App\Models\User;
use App\Models\Workout;
use App\Tenancy\CurrentBox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public static function calendarMonths(): array
    {
        return [
            ['2026-02', 28, '2026-01-26', '2026-03-01'],
            ['2028-02', 29, '2028-01-31', '2028-03-05'],
            ['2026-04', 30, '2026-03-30', '2026-05-03'],
            ['2026-12', 31, '2026-11-30', '2027-01-03'],
        ];
    }

    #[DataProvider('calendarMonths')]
    public function test_month_grid_groups_all_days_in_monday_to_sunday_rows(string $month, int $daysInMonth, string $first, string $last): void
    {
        $days = $this->actingAs(User::factory()->create())->get(route('schedule', ['month' => $month]))
            ->assertOk()->viewData('calendarDays');

        $this->assertSame($first, $days[0]->toDateString());
        $this->assertSame($last, $days[array_key_last($days)]->toDateString());
        $this->assertSame(0, count($days) % 7);
        $this->assertCount($daysInMonth, array_filter($days, fn ($day) => $day->format('Y-m') === $month));
    }

    public function test_month_navigation_keeps_week_and_filter_until_date_selection_and_today_uses_box_timezone(): void
    {
        $this->travelTo(Carbon::parse('2027-01-01 01:00:00', 'UTC'));
        $user = User::factory()->for(Box::factory()->state(['timezone' => 'America/Sao_Paulo']))->create();
        $filter = ['modality' => 'crossfit'];
        $this->actingAs($user)->get(route('schedule', $filter))->assertOk()->assertSee('dezembro 2026')
            ->assertViewHas('selected', fn ($day) => $day->toDateString() === '2026-12-31');

        $january = route('schedule', [...$filter, 'date' => '2026-12-10', 'month' => '2027-01']);
        $this->get($january)->assertOk()->assertSee('janeiro 2027')
            ->assertViewHas('start', fn ($day) => $day->toDateString() === '2026-12-07')
            ->assertSee(route('schedule', ['date' => '2026-12-10', ...$filter, 'month' => '2026-12']), true)
            ->assertSee(route('schedule', ['date' => '2026-12-10', ...$filter, 'month' => '2027-02']), true);
        $selected = route('schedule', [...$filter, 'date' => '2026-12-10']);
        $this->get($selected)->assertOk()->assertViewHas('end', fn ($day) => $day->toDateString() === '2026-12-13')
            ->assertSee(route('workouts.create', ['date' => '2026-12-10', 'modality' => 'crossfit', 'structure' => 'full']), true)
            ->assertSee('data-month-date="2026-12-10"', false)->assertSee('data selecionada, semana exibida');
        $this->get($selected)->assertOk()->assertViewHas('selected', fn ($day) => $day->toDateString() === '2026-12-10');
        $this->get(route('schedule', ['month' => '2026-13']))->assertSessionHasErrors('month');
    }

    public function test_markers_follow_filter_active_modalities_tenant_and_workout_lifecycle(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $input = [...$this->workoutInput(), 'scheduled_for' => '2026-12-10'];
        $this->createWorkoutFor($other, [...$input, 'scheduled_for' => '2026-12-09', 'title' => 'Outra box']);
        $this->createWorkoutFor($user, [...$this->workoutInput('hyrox'), 'scheduled_for' => '2026-12-11', 'title' => 'HYROX desativado']);
        $this->createWorkoutFor($user, [...$this->workoutInput('functional'), 'scheduled_for' => '2026-12-12']);
        $hyrox = TrainingModality::where('slug', 'hyrox')->firstOrFail();
        $user->box->modalities()->detach($hyrox->id);

        $url = route('schedule', ['date' => '2026-12-10']);
        $crossfit = route('schedule', ['date' => '2026-12-10', 'modality' => 'crossfit']);
        $this->actingAs($user)->get($url)->assertOk()->assertDontSee('Outra box')->assertSee('HYROX desativado')
            ->assertViewHas('markedDates', ['2026-12-12' => 1]);
        $this->get($crossfit)->assertOk()->assertViewHas('markedDates', []);
        $saved = $this->post(route('workouts.store'), [...$input, 'status' => 'draft'])->assertSessionHasNoErrors()->headers->get('Location');
        $workout = $this->get($saved)->assertOk()->assertSee($url, false)->viewData('workout');
        $this->get($crossfit)->assertOk()->assertViewHas('markedDates', ['2026-12-10' => 1])->assertSee('com treinos, data selecionada');
        $this->createWorkoutFor($user, [...$this->workoutInput('functional'), 'scheduled_for' => '2026-12-10']);
        $this->get($url)->assertOk()->assertViewHas('markedDates', ['2026-12-10' => 2, '2026-12-12' => 1])
            ->assertSee('com treinos em 2 modalidades');
        $this->delete(route('workouts.destroy', $workout), ['confirmation' => 1])->assertRedirect($url);
        $this->get($crossfit)->assertOk()->assertViewHas('markedDates', []);
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
