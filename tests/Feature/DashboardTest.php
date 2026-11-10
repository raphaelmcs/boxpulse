<?php

namespace Tests\Feature;

use App\Models\Box;
use App\Models\User;
use App\Models\Workout;
use App\Tenancy\CurrentBox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_today_uses_the_box_timezone_and_supports_multiple_published_workouts(): void
    {
        $this->travelTo(Carbon::parse('2026-11-11 01:30:00', 'UTC'));
        $box = Box::factory()->create(['timezone' => 'America/Sao_Paulo']);
        $user = User::factory()->for($box)->create();
        app(CurrentBox::class)->set($box->id);
        Workout::factory()->create(['title' => 'Today first', 'scheduled_for' => '2026-11-10', 'status' => 'published']);
        Workout::factory()->create(['title' => 'Today second', 'scheduled_for' => '2026-11-10', 'status' => 'published']);
        Workout::factory()->create(['title' => 'Tomorrow only', 'scheduled_for' => '2026-11-11', 'status' => 'published']);
        Workout::factory()->create(['title' => 'Private draft', 'scheduled_for' => '2026-11-10', 'status' => 'draft']);
        app(CurrentBox::class)->clear();
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('10.11.2026')->assertSee('terça-feira')->assertSee('Today first')->assertSee('Today second')->assertSee('Tomorrow only')->assertSee('Private draft')->assertSee('Próximos treinos')->assertSee('Rascunhos de hoje');
    }

    public function test_dashboard_shows_empty_state_and_all_modalities(): void
    {
        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk()->assertSee('CrossFit')->assertSee('HYROX')->assertSee('Treinamento Funcional')->assertSee('Ainda não há treinos publicados');
    }

    public function test_user_content_is_escaped_in_html(): void
    {
        $user = User::factory()->create(['name' => '<script>alert(1)</script>']);
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }
}
