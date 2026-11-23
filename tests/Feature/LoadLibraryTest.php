<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoadLibraryTest extends TestCase
{
    use RefreshDatabase;

    private function preset(array $overrides = []): array
    {
        return array_replace([
            'equipment' => 'Kettlebell', 'movement' => 'Kettlebell Swing', 'unit' => 'kg', 'active' => true,
            'levels' => ['rx' => ['male' => 24, 'female' => 16], 'intermediate' => ['male' => 20, 'female' => 12], 'scale' => ['male' => 0, 'female' => 8]],
            'reference' => 'Bloco de resistência da box',
        ], $overrides);
    }

    public function test_base_library_is_shared_read_only_and_custom_equipment_and_presets_are_box_scoped(): void
    {
        $owner = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['role' => 'admin']);
        $this->actingAs($owner)->getJson(route('loads.index'))->assertOk()->assertJsonFragment(['origin' => 'base', 'movement' => 'Dumbbell Snatch']);
        $this->postJson(route('loads.store'), $this->preset(['box_id' => $other->box_id]))->assertUnprocessable();
        $preset = $this->postJson(route('loads.store'), $this->preset())->assertCreated()->assertJsonPath('origin', 'box')->assertJsonPath('levels.scale.male', 0)->json();
        $this->postJson(route('loads.store'), $this->preset(['equipment' => 'Slamball', 'movement' => null, 'reference' => null, 'levels' => []]))->assertCreated();
        $this->assertDatabaseHas('load_presets', ['id' => $preset['id'], 'box_id' => $owner->box_id]);
        $this->getJson(route('loads.index'))->assertOk()->assertJsonFragment(['equipment' => 'Slamball']);
        $this->actingAs($other)->getJson(route('loads.index'))->assertOk()->assertJsonMissing(['equipment' => 'Slamball'])->assertJsonMissing(['reference' => $preset['reference']]);
        $this->putJson(route('loads.update', $preset['id']), $this->preset())->assertNotFound();
        $this->putJson(route('loads.update', 0), $this->preset())->assertNotFound();
        $coach = User::factory()->create(['box_id' => $owner->box_id, 'role' => 'coach']);
        $this->actingAs($coach)->getJson(route('loads.index'))->assertOk()->assertJsonFragment(['reference' => $preset['reference']]);
        $this->postJson(route('loads.store'), $this->preset())->assertForbidden();
        $this->putJson(route('loads.update', $preset['id']), $this->preset())->assertForbidden();

        $this->actingAs($owner)->putJson(route('loads.update', $preset['id']), $this->preset(['active' => false]))->assertOk()->assertJsonPath('active', false);
        $this->actingAs($coach)->getJson(route('loads.index'))->assertOk()->assertJsonMissing(['reference' => $preset['reference']]);
    }

    public function test_analysis_lists_box_presets_and_prescribed_loads_without_writing(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user)->postJson(route('loads.store'), $this->preset())->assertCreated();
        $input = ['type' => 'skill', 'description' => "12 KB Swing\n40 DB SNATCH 22,5/15 KG\n5 Front Squats 70%\n500 m Run\nAMRAP 20'"];
        $this->postJson(route('loads.analyze'), $input)->assertOk()->assertJsonCount(3, 'items')
            ->assertJsonPath('items.0.block', 'skill')->assertJsonPath('items.0.presets.0.origin', 'box')
            ->assertJsonPath('items.1.explicit', ['male' => '22,5', 'female' => '15', 'unit' => 'kg'])
            ->assertJsonPath('items.1.presets.0.origin', 'base')
            ->assertJsonPath('items.2.relative', '70%')->assertJsonPath('items.2.explicit', null)->assertJsonPath('items.2.presets', []);
        $this->actingAs(User::factory()->create())->postJson(route('loads.analyze'), $input)->assertOk()->assertJsonPath('items.0.presets', []);
        $this->assertDatabaseCount('workouts', 0);
        $this->assertDatabaseCount('load_presets', 1);
    }

    public function test_custom_movement_is_private_and_contextual_presets_exclude_generic_fallbacks(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        $coach = User::factory()->create(['box_id' => $owner->box_id, 'role' => 'coach']);
        $movementInput = ['name' => 'Clean Pausado', 'equipment' => 'Barbell', 'variation' => 'Pausa',
            'aliases' => ['PCP'], 'modalities' => ['crossfit'], 'active' => true];
        $this->actingAs($owner)->get(route('box.settings'))->assertOk()->assertSee('Biblioteca de Movimentos e Cargas');
        $this->getJson(route('loads.index'))->assertJsonFragment(['name' => 'Power Clean'])->assertJsonFragment(['name' => 'Plate Ground to Overhead']);
        $movement = $this->postJson(route('loads.movements.store'), $movementInput)->assertCreated()
            ->assertJsonPath('normalized_name', 'clean pausado')->json();
        $this->postJson(route('loads.movements.store'), [...$movementInput, 'name' => 'CLEAN PAUSADO'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson(route('loads.store'), $this->preset(['equipment' => 'Barbell', 'movement' => null, 'name' => 'Carga genérica']))->assertCreated();
        $association = ['movement_id' => $movement['id'], 'equipment' => 'Barbell', 'movement' => 'Clean Pausado'];
        $this->postJson(route('loads.store'), $this->preset([...$association, 'name' => 'Livre']))->assertCreated();
        $this->postJson(route('loads.store'), $this->preset([...$association, 'name' => 'Força leve', 'context' => 'strength', 'notes' => 'Pausa de dois segundos',
            'levels' => ['rx' => ['male' => '22,5', 'female' => 0]]]))->assertCreated()->assertJsonPath('levels.rx.male', '22.5')->assertJsonPath('levels.rx.female', 0);

        $analysis = ['type' => 'workout', 'modality' => 'crossfit', 'title' => 'Força', 'description' => "8 PCP\n12 Power Clean"];
        $this->actingAs($coach)->postJson(route('loads.analyze'), $analysis)->assertOk()
            ->assertJsonPath('items.0.movement', 'Clean Pausado')->assertJsonPath('items.0.variation', 'Pausa')
            ->assertJsonPath('items.0.presets.0.name', 'Força leve')->assertJsonPath('items.0.presets.1.name', 'Livre')
            ->assertJsonCount(2, 'items.0.presets')->assertJsonPath('items.1.presets', []);
        $this->postJson(route('loads.movements.store'), $movementInput)->assertForbidden();
        $this->actingAs($foreign)->postJson(route('loads.store'), $this->preset($association))->assertUnprocessable()->assertJsonValidationErrors('movement_id');
        $this->putJson(route('loads.movements.update', $movement['id']), $movementInput)->assertNotFound();
        $this->postJson(route('loads.analyze'), $analysis)->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.movement', 'Power Clean');
        $this->actingAs($owner)->putJson(route('loads.movements.update', $movement['id']), [...$movementInput, 'active' => false])->assertOk();
        $this->actingAs($coach)->getJson(route('loads.index'))->assertOk()->assertJsonMissing(['name' => 'Força leve']);
    }
}
