<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithWorkouts;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use InteractsWithWorkouts, RefreshDatabase;

    public function test_login_always_opens_environment_selection_including_a_previous_bookmark(): void
    {
        $user = User::factory()->create();
        $this->get(route('schedule'))->assertRedirect(route('login'));
        $this->post(route('login.store'), ['username' => $user->username, 'password' => 'password'])->assertRedirect(route('workspace'));
        $this->assertAuthenticatedAs($user);
        $this->assertNull(session('url.intended'));
        $this->get(route('workspace'))->assertOk()->assertSee('Gerenciar treinos')->assertSee('Iniciar aula')->assertSee(route('manage.dashboard'), false)->assertSee(route('coach.today'), false)->assertDontSee('Gestão da Box</nav>', false);
    }

    public function test_workspace_has_the_authenticated_box_identity_and_no_administrative_menu(): void
    {
        $user = User::factory()->create();
        $user->box->update(['name' => 'Minha comunidade', 'primary_color' => '#F97316', 'logo_path' => "boxes/{$user->box_id}/logo.png"]);
        $this->actingAs($user)->get(route('workspace'))->assertOk()->assertSee('Minha comunidade')->assertSee('dashboard-box-logo')->assertSee('--box-primary:#F97316', false)->assertDontSee('workspace-nav')->assertDontSee('header-box-logo');
        $this->get(route('home'))->assertRedirect(route('workspace'));
    }

    public function test_guest_and_user_without_box_cannot_enter_any_workspace(): void
    {
        foreach (['workspace', 'manage.dashboard', 'coach.today'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
        $user = User::factory()->create(['box_id' => null]);
        foreach (['workspace', 'manage.dashboard', 'coach.today'] as $route) {
            $this->actingAs($user)->get(route($route))->assertForbidden();
        }
    }

    public function test_management_reuses_dashboard_and_displays_actual_drafts_and_upcoming_records(): void
    {
        $user = User::factory()->create();
        $today = now($user->box->timezone)->toDateString();
        $tomorrow = now($user->box->timezone)->addDay()->toDateString();
        $published = $this->createWorkoutFor($user, [...$this->workoutInput(), 'title' => 'Publicado de hoje', 'scheduled_for' => $today]);
        $draft = $this->createWorkoutFor($user, [...$this->workoutInput(), 'title' => 'Rascunho de hoje', 'scheduled_for' => $today, 'status' => 'draft']);
        $next = $this->createWorkoutFor($user, [...$this->workoutInput(), 'title' => 'Aula de amanhã', 'scheduled_for' => $tomorrow]);
        $foreign = User::factory()->create();
        $this->createWorkoutFor($foreign, [...$this->workoutInput(), 'title' => 'Foreign box', 'scheduled_for' => $today]);
        foreach (['manage.dashboard', 'dashboard'] as $route) {
            $response = $this->actingAs($user)->get(route($route))->assertOk()->assertSee('GESTÃO DA BOX')->assertSee('Próximos treinos')->assertSee($published->title)->assertSee($draft->title)->assertSee($next->title)->assertSee('Voltar à escolha de ambientes')->assertDontSee('Foreign box');
            $this->assertSame([$published->id], $response->viewData('workouts')->modelKeys());
            $this->assertSame([$draft->id], $response->viewData('drafts')->modelKeys());
            $this->assertSame([$next->id], $response->viewData('upcoming')->modelKeys());
        }
    }

    public function test_existing_management_routes_stay_accessible_and_coach_cannot_open_settings(): void
    {
        $user = User::factory()->create(['role' => 'coach']);
        $workout = $this->createWorkoutFor($user);
        foreach (['dashboard' => [], 'schedule' => [], 'workouts.index' => [], 'workouts.create' => [], 'sessions.index' => [], 'workouts.show' => $workout, 'workouts.edit' => $workout, 'workouts.preview' => $workout] as $route => $parameters) {
            $this->actingAs($user)->get(route($route, $parameters))->assertOk();
        }
        $this->get(route('box.settings'))->assertForbidden();
        $this->get(route('manage.dashboard'))->assertOk()->assertDontSee('href="'.route('box.settings').'"', false);
    }
}
