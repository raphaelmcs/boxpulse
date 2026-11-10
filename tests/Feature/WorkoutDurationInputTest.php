<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithWorkouts;
use Tests\TestCase;

class WorkoutDurationInputTest extends TestCase
{
    use InteractsWithWorkouts, RefreshDatabase;

    private function minuteInput(string $format = 'amrap', mixed $minutes = 12): array
    {
        $data = $this->workoutInput();
        $data['blocks'][0]['timer_type'] = $format;
        unset($data['blocks'][0]['duration_seconds']);
        $data['blocks'][0]['duration_minutes'] = $minutes;
        $data['blocks'][0]['duration_extra_seconds'] = 0;
        if ($format === 'emom') {
            $data['blocks'][0]['timer_settings']['interval_seconds'] = 60;
        }

        return $data;
    }

    public function test_full_workout_converts_warmup_skill_and_wod_minutes_once(): void
    {
        $data = $this->workoutInput('crossfit', 'full');
        foreach ([6, 10, 12] as $index => $minutes) {
            $data['blocks'][$index]['timer_type'] = $index === 2 ? 'amrap' : 'countdown';
            unset($data['blocks'][$index]['duration_seconds']);
            $data['blocks'][$index]['duration_minutes'] = $minutes;
        }
        $this->actingAs(User::factory()->create())->post(route('workouts.store'), $data)->assertSessionHasNoErrors();
        foreach ([360, 600, 720] as $position => $seconds) {
            $this->assertDatabaseHas('workout_blocks', ['position' => $position, 'duration_seconds' => $seconds]);
        }
    }

    public static function formats(): array
    {
        return [['for_time', 15, 900], ['amrap', 12, 720], ['emom', 20, 1200]];
    }

    #[DataProvider('formats')]
    public function test_main_durations_use_minutes_and_internal_timer_contract_stays_seconds(string $format, int $minutes, int $seconds): void
    {
        $this->actingAs(User::factory()->create())->post(route('workouts.store'), $this->minuteInput($format, $minutes))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('workout_blocks', ['timer_type' => $format, 'duration_seconds' => $seconds]);
        if ($format === 'emom') {
            $this->assertDatabaseHas('workout_blocks', ['timer_settings->interval_seconds' => 60]);
        }
    }

    public function test_edit_displays_minutes_and_preserves_legacy_odd_seconds_exactly(): void
    {
        $user = User::factory()->create();
        $input = $this->workoutInput();
        $input['blocks'][0]['duration_seconds'] = 91;
        $workout = $this->createWorkoutFor($user, $input);
        $this->actingAs($user)->get(route('workouts.edit', $workout))->assertOk()->assertSee('name="blocks[0][duration_minutes]" value="1"', false)->assertSee('name="blocks[0][duration_extra_seconds]" value="31"', false);
        $data = $this->minuteInput('for_time', 1);
        $data['blocks'][0]['duration_extra_seconds'] = 31;
        $this->put(route('workouts.update', $workout), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('workout_blocks', ['workout_id' => $workout->id, 'duration_seconds' => 91]);
        $data['blocks'][0]['duration_minutes'] = 15;
        $data['blocks'][0]['duration_extra_seconds'] = 0;
        $this->put(route('workouts.update', $workout), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('workout_blocks', ['workout_id' => $workout->id, 'duration_seconds' => 900]);
    }

    public function test_invalid_fraction_negative_or_mixed_units_are_rejected_and_input_is_preserved(): void
    {
        $this->actingAs(User::factory()->create())->post(route('workouts.store'), $this->minuteInput(minutes: '1.5'))->assertSessionHasErrors('blocks.0.duration_minutes')->assertSessionHasInput('blocks.0.duration_minutes', '1.5');
        $this->post(route('workouts.store'), $this->minuteInput(minutes: -1))->assertSessionHasErrors('blocks.0.duration_minutes');
        $mixed = $this->minuteInput();
        $mixed['blocks'][0]['duration_seconds'] = 720;
        $this->post(route('workouts.store'), $mixed)->assertSessionHasErrors('blocks.0.duration_minutes');
        $this->assertDatabaseCount('workouts', 0);
    }

    public function test_short_intervals_preparation_and_station_times_remain_seconds(): void
    {
        $data = $this->workoutInput('functional');
        $data['blocks'][0] = ['type' => 'workout', 'description' => 'Circuit', 'timer_type' => 'circuit', 'timer_settings' => ['work_seconds' => 45, 'rest_seconds' => 15, 'transition_seconds' => 5, 'preparation_seconds' => 10, 'rounds' => 2], 'stations' => [['name' => 'Row', 'duration_seconds' => 30]]];
        $this->actingAs(User::factory()->create())->post(route('workouts.store'), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('workout_blocks', ['duration_seconds' => null, 'timer_settings->work_seconds' => 45, 'timer_settings->rest_seconds' => 15, 'timer_settings->preparation_seconds' => 10]);
        $this->assertDatabaseHas('workout_stations', ['duration_seconds' => 30]);
    }

    public function test_minutes_are_preserved_after_validation_without_optional_remainder_field(): void
    {
        $data = $this->minuteInput();
        unset($data['blocks'][0]['duration_extra_seconds']);
        $data['blocks'][0]['description'] = str_repeat('x', 20001);
        $this->actingAs(User::factory()->create())->from(route('workouts.create'))->post(route('workouts.store'), $data)->assertSessionHasErrors('blocks.0.description');
        $this->get(route('workouts.create'))->assertOk()->assertSee('name="blocks[0][duration_minutes]" value="12"', false);
    }
}
