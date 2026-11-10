<?php

namespace Tests\Feature;

use App\Models\User;
use App\Workouts\TimerConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithSessions;
use Tests\TestCase;

class UniversalTimerFormatsTest extends TestCase
{
    use InteractsWithSessions, RefreshDatabase;

    public static function formatsAndBlocks(): array
    {
        $cases = [];
        foreach (['crossfit', 'hyrox', 'functional'] as $modality) {
            foreach (['warmup', 'skill', 'workout'] as $block) {
                foreach (array_keys(TimerConfiguration::LABELS) as $format) {
                    $cases["$modality-$block-$format"] = [$modality, $block, $format];
                }
            }
        }

        return $cases;
    }

    public function test_emom_uses_the_registry_default_when_interval_is_omitted(): void
    {
        $input = $this->workoutInput('crossfit', 'full');
        $input['blocks'] = [['type' => 'warmup', 'description' => '10 burpees por minuto', 'timer_type' => 'emom', 'duration_seconds' => 120]];
        [, $state] = $this->sessionFor(User::factory()->create(), $input);
        $this->assertSame(60000, $state['timers'][0]['config']['interval_ms']);
        $this->assertDatabaseHas('workout_blocks', ['timer_settings->interval_seconds' => 60]);
    }

    #[DataProvider('formatsAndBlocks')]
    public function test_every_format_has_the_same_lifecycle_in_every_block_and_modality(string $modality, string $type, string $format): void
    {
        $user = User::factory()->create();
        $input = $this->workoutInput($modality, 'full', 'draft');
        $input['blocks'] = [[
            'type' => $type, 'title' => 'Etapa universal', 'description' => "10 burpees\n20 air squats", 'timer_type' => $format,
            'duration_seconds' => in_array($format, ['stopwatch', 'countdown', 'for_time', 'amrap', 'emom']) ? 120 : null,
            'timer_settings' => array_intersect_key(['preparation_seconds' => 0, 'interval_seconds' => 60, 'work_seconds' => 20, 'rest_seconds' => 10, 'rounds' => 3, 'transition_seconds' => 5, 'stop_at_cap' => true], array_flip(['preparation_seconds', ...TimerConfiguration::settingKeys($format)])),
            'stations' => $format === 'circuit' ? [['name' => 'Bike', 'duration_seconds' => 15], ['name' => 'Row', 'duration_seconds' => 15]] : [],
        ]];
        $workout = $this->createWorkoutFor($user, $input);
        $this->actingAs($user)->put(route('workouts.update', $workout), [...$input, 'title' => 'Atualizado'])->assertSessionHasNoErrors();
        $this->post(route('workouts.publish', $workout))->assertSessionHasNoErrors();
        $this->post(route('coach.prepare', $workout))->assertSessionHasNoErrors();
        $session = $this->get(route('sessions.index'))->viewData('sessions')->first();
        $state = $this->postJson(route('sessions.lease', $session), ['client_id' => (string) Str::uuid()])->assertOk()->json('data');
        $this->assertSame($type, $state['timers'][0]['block_type']);
        $this->assertSame($format, $state['timers'][0]['config']['type']);
        $this->assertSame(1, $state['timers'][0]['config']['version']);
        if ($format === 'emom') {
            $this->assertSame(120000, $state['timers'][0]['config']['duration_ms']);
            $this->assertSame(60000, $state['timers'][0]['config']['interval_ms']);
        }
        $state = $this->command($state, 'start')->assertOk()->json('data');
        $state = $this->command($state, 'pause')->assertOk()->assertJsonPath('data.timers.0.state', 'paused')->json('data');
        $this->getJson(route('sessions.state', $session))->assertOk()->assertJsonPath('data.timers.0.state', 'paused');
        $state = $this->command($state, 'resume')->assertOk()->json('data');
        $this->get(route('sessions.tv', $session))->assertOk()->assertDontSee('data-page-next', false);
        $this->command($state, 'finish')->assertOk()->assertJsonPath('data.status', 'completed');
    }
}
