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
        $publish->handle($request->user(), $workout, 'published', $this->expectedVersion($request));

        return to_route('workouts.show', $workout)->with('success', 'Treino publicado e disponível na programação.');
    }

    public function draft(Request $request, Workout $workout, PublishWorkout $publish): RedirectResponse
    {
        $publish->handle($request->user(), $workout, 'draft', $this->expectedVersion($request));

        return to_route('workouts.show', $workout)->with('success', 'Treino retornou a rascunho.');
    }

    private function expectedVersion(Request $request): ?int
    {
        $data = $request->validate(['expected_edit_version' => ['sometimes', 'required', 'integer', 'min:1']]);

        return isset($data['expected_edit_version']) ? (int) $data['expected_edit_version'] : null;
    }
}
