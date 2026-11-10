<?php

namespace App\Http\Controllers;

use App\Actions\PublishWorkout;
use App\Models\Workout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkoutStatusController extends Controller
{
    public function publish(Request $request, Workout $workout, PublishWorkout $publish): RedirectResponse
    {
        $publish->handle($request->user(), $workout, 'published');

        return to_route('workouts.show', $workout)->with('success', 'Treino publicado e disponível na programação.');
    }

    public function draft(Request $request, Workout $workout, PublishWorkout $publish): RedirectResponse
    {
        $publish->handle($request->user(), $workout, 'draft');

        return to_route('workouts.show', $workout)->with('success', 'Treino retornou a rascunho.');
    }
}
