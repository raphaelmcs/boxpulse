<?php

namespace App\Http\Controllers;

use App\Actions\DuplicateWorkout;
use App\Http\Requests\DuplicateWorkoutRequest;
use App\Models\Workout;
use Illuminate\Http\RedirectResponse;

class WorkoutCopyController extends Controller
{
    public function store(DuplicateWorkoutRequest $request, Workout $workout, DuplicateWorkout $copy): RedirectResponse
    {
        $duplicate = $copy->handle($request->user(), $workout, $request->validated('scheduled_for'));

        return to_route('workouts.edit', $duplicate)->with('success', 'Cópia criada como rascunho. Revise antes de publicar.');
    }
}
