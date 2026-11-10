<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithWorkouts;
use Tests\TestCase;

class DashboardIdentityTest extends TestCase
{
    use InteractsWithWorkouts, RefreshDatabase;

    public function test_dashboard_has_prominent_identity_without_duplicate_header_logo(): void
    {
        $user = User::factory()->create();
        $user->box->update(['name' => 'Minha Box', 'head_coach_name' => 'Ana Coach', 'logo_path' => "boxes/{$user->box_id}/logo.png", 'primary_color' => '#F97316', 'secondary_color' => '#334155']);
        $data = $this->workoutInput();
        $data['scheduled_for'] = now($user->box->timezone)->toDateString();
        $workout = $this->createWorkoutFor($user, $data);
        $response = $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('dashboard-box-logo')->assertSee('Head Coach · Ana Coach')->assertSee('Minha Box')->assertSee('Iniciar aula de hoje')->assertSee(route('coach.today'), false)->assertSee('--box-primary:#F97316', false)->assertDontSee('header-box-logo');
        $this->assertSame(1, substr_count($response->getContent(), 'class="dashboard-box-logo"'));
    }

    public function test_multiple_today_workouts_require_a_choice_and_empty_day_offers_registration(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Cadastrar treino de hoje');
        $data = [...$this->workoutInput(), 'scheduled_for' => now($user->box->timezone)->toDateString()];
        $this->createWorkoutFor($user, $data);
        $this->createWorkoutFor($user, $data);
        $this->get(route('dashboard'))->assertOk()->assertSee(route('coach.today'), false)->assertSee('Escolha o treino no Modo Aula.');
    }
}
