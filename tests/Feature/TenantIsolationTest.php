<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\User;
use App\Models\Workout;
use App\Tenancy\CurrentBox;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use LogicException;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function workoutFor(Box $box, array $attributes = []): Workout
    {
        $context = app(CurrentBox::class);
        $context->set($box->id);
        try {
            return Workout::factory()->create($attributes);
        } finally {
            $context->clear();
        }
    }

    public function test_route_binding_and_dashboard_cannot_leak_other_box_workouts(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ownWorkout = $this->workoutFor($owner->box, ['title' => 'Own workout', 'status' => 'published']);
        $foreignWorkout = $this->workoutFor($other->box, ['title' => 'Secret workout', 'status' => 'published']);
        $this->actingAs($owner)->get(route('dashboard'))->assertOk()->assertSee('Own workout')->assertDontSee('Secret workout');
        $this->get(route('workouts.show', $ownWorkout))->assertOk();
        $this->get(route('workouts.show', $foreignWorkout))->assertNotFound();
        $this->assertNull(app(CurrentBox::class)->id());
        $this->actingAs($other)->get(route('dashboard'))->assertOk()->assertSee('Secret workout')->assertDontSee('Own workout');
    }

    public function test_query_parameters_and_headers_cannot_choose_the_tenant(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $foreignWorkout = $this->workoutFor($other->box, ['title' => 'Do not expose']);
        $this->actingAs($owner)->withHeader('X-Box-Id', (string) $other->box_id)->get(route('workouts.show', $foreignWorkout).'?box_id='.$other->box_id)->assertNotFound();
    }

    public function test_reads_without_a_tenant_return_no_rows(): void
    {
        $this->workoutFor(Box::factory()->create());
        $this->assertSame(0, Workout::count());
    }

    public function test_writes_without_a_tenant_are_rejected(): void
    {
        $this->expectException(LogicException::class);
        Workout::factory()->create();
    }

    public function test_creation_infers_box_id_from_the_context(): void
    {
        $box = Box::factory()->create();
        $workout = $this->workoutFor($box);
        $this->assertSame($box->id, $workout->box_id);
    }

    public function test_explicit_cross_tenant_creation_is_rejected(): void
    {
        $owner = Box::factory()->create();
        $foreign = Box::factory()->create();
        app(CurrentBox::class)->set($owner->id);
        $this->expectException(AuthorizationException::class);
        Workout::factory()->create(['box_id' => $foreign->id]);
    }

    public function test_workouts_cannot_be_reassigned_to_another_box(): void
    {
        $owner = Box::factory()->create();
        $foreign = Box::factory()->create();
        $workout = $this->workoutFor($owner);
        app(CurrentBox::class)->set($owner->id);
        $workout->box_id = $foreign->id;
        $this->expectException(AuthorizationException::class);
        $workout->save();
    }

    public function test_a_loaded_foreign_model_cannot_be_updated_or_deleted(): void
    {
        $owner = Box::factory()->create();
        $foreign = Box::factory()->create();
        $workout = $this->workoutFor($foreign);
        app(CurrentBox::class)->set($owner->id);
        try {
            $workout->update(['title' => 'Changed']);
            $this->fail('Foreign model update must fail.');
        } catch (AuthorizationException) {
            $this->assertDatabaseHas('workouts', ['id' => $workout->id, 'title' => $workout->getRawOriginal('title')]);
        }
        $this->expectException(AuthorizationException::class);
        $workout->delete();
    }

    public function test_policies_deny_cross_box_access_even_for_a_loaded_model(): void
    {
        $owner = User::factory()->create();
        $foreignWorkout = $this->workoutFor(Box::factory()->create());
        $this->assertFalse(Gate::forUser($owner)->allows('view', $foreignWorkout));
        $this->assertFalse(Gate::forUser($owner)->allows('update', $foreignWorkout));
        $this->assertFalse(Gate::forUser($owner)->allows('delete', $foreignWorkout));
    }

    public function test_coaches_can_view_but_cannot_delete_a_workout(): void
    {
        $coach = User::factory()->create(['role' => 'coach']);
        $workout = $this->workoutFor($coach->box);
        $this->assertTrue(Gate::forUser($coach)->allows('view', $workout));
        $this->assertTrue(Gate::forUser($coach)->allows('update', $workout));
        $this->assertFalse(Gate::forUser($coach)->allows('delete', $workout));
    }

    public function test_unknown_roles_cannot_view_workouts(): void
    {
        $user = User::factory()->create(['role' => 'unknown']);
        $workout = $this->workoutFor($user->box);
        $this->actingAs($user)->get(route('workouts.show', $workout))->assertForbidden();
    }

    public function test_tenant_cannot_be_switched_during_an_operation(): void
    {
        app(CurrentBox::class)->set(1);
        $this->expectException(LogicException::class);
        app(CurrentBox::class)->set(2);
    }
}
