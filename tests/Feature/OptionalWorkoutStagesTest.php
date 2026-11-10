<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithSessions;
use Tests\TestCase;

class OptionalWorkoutStagesTest extends TestCase
{
    use InteractsWithSessions, RefreshDatabase;

    public static function combinations(): array
    {
        $cases = [];
        foreach (['crossfit', 'hyrox', 'functional'] as $modality) {
            foreach ([['warmup'], ['skill'], ['workout'], ['warmup', 'skill'], ['warmup', 'workout'], ['skill', 'workout'], ['warmup', 'skill', 'workout']] as $types) {
                $cases[$modality.'-'.implode('-', $types)] = [$modality, $types];
            }
        }

        return $cases;
    }

    #[DataProvider('combinations')]
    public function test_any_nonempty_ordered_subset_can_publish_prepare_and_navigate(string $modality, array $types): void
    {
        $user = User::factory()->create();
        $input = [...$this->workoutInput($modality, 'full'), 'scheduled_for' => now($user->box->timezone)->toDateString()];
        $input['blocks'] = array_values(array_filter($input['blocks'], fn ($block) => in_array($block['type'], $types)));
        [$workout, $state] = $this->sessionFor($user, $input);
        $this->assertSame($types, array_column($state['timers'], 'block_type'));
        $this->assertDatabaseCount('workout_blocks', count($types));
        $this->assertDatabaseCount('timer_sessions', count($types));
        for ($position = 1; $position < count($types); $position++) {
            $state = $this->command($state, 'next')->assertOk()->assertJsonPath('data.current_position', $position)->json('data');
        }
        $this->command($state, 'next')->assertUnprocessable();
        if (count($types) > 1) {
            $state = $this->command($state, 'previous')->assertOk()->json('data');
        } else {
            $this->command($state, 'previous')->assertUnprocessable();
        }
        $this->get(route('coach.today'))->assertOk()->assertDontSee('Bloco principal não informado');
        $this->get(route('workouts.edit', $workout))->assertOk();
        $this->get(route('sessions.tv', $state['id']))->assertOk();
        $this->getJson(route('sessions.state', $state['id']))->assertOk()->assertJsonCount(count($types), 'data.timers');
        $this->command($state, 'finish')->assertOk()->assertJsonPath('data.status', 'completed');
    }

    public function test_empty_stages_are_not_persisted_and_publication_requires_content(): void
    {
        $user = User::factory()->create();
        $input = $this->workoutInput('crossfit', 'full', 'draft');
        $empty = ['type' => 'warmup', 'title' => ' ', 'description' => '', 'notes' => '', 'timer_type' => 'countdown', 'duration_seconds' => 360];
        $input['blocks'] = [$empty];
        $this->actingAs($user)->post(route('workouts.store'), $input)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('workout_blocks', 0);
        $this->post(route('workouts.store'), [...$input, 'status' => 'published'])->assertSessionHasErrors('blocks');
        $this->post(route('workouts.store'), [...$input, 'status' => 'published', 'blocks' => []])->assertSessionHasErrors('blocks');
        $this->assertDatabaseCount('workouts', 1);
    }

    public function test_removing_skill_keeps_other_blocks_and_old_snapshot_unchanged(): void
    {
        $user = User::factory()->create();
        [$workout, $old] = $this->sessionFor($user, $this->workoutInput('crossfit', 'full'));
        $data = $this->workoutInput('crossfit', 'full');
        $data['blocks'] = [$data['blocks'][0], $data['blocks'][2]];
        $this->put(route('workouts.update', $workout), $data)->assertSessionHasNoErrors();
        $this->post(route('coach.prepare', $workout))->assertSessionHasNoErrors();
        $new = $this->get(route('sessions.index'))->viewData('sessions')->first();
        $this->getJson(route('sessions.state', $new))->assertOk()->assertJsonCount(2, 'data.timers')->assertJsonPath('data.timers.0.block_type', 'warmup')->assertJsonPath('data.timers.1.block_type', 'workout');
        $this->getJson(route('sessions.state', $old['id']))->assertOk()->assertJsonCount(3, 'data.timers')->assertJsonPath('data.timers.1.block_type', 'skill');
        $this->assertDatabaseHas('workout_blocks', ['workout_id' => $workout->id, 'type' => 'workout', 'position' => 1]);
        $this->get(route('workouts.edit', $workout))->assertOk()->assertSee('data-block-toggle="skill"', false);
    }

    public function test_reversed_duplicates_and_simplified_non_workout_blocks_remain_invalid(): void
    {
        $this->actingAs(User::factory()->create());
        $data = $this->workoutInput('crossfit', 'full');
        $this->post(route('workouts.store'), [...$data, 'blocks' => array_reverse($data['blocks'])])->assertSessionHasErrors('blocks');
        $this->post(route('workouts.store'), [...$data, 'blocks' => [$data['blocks'][0], $data['blocks'][0]]])->assertSessionHasErrors();
        $this->post(route('workouts.store'), [...$data, 'structure' => 'main', 'blocks' => [$data['blocks'][0]]])->assertSessionHasErrors('blocks');
        $this->assertDatabaseCount('workouts', 0);
    }
}
