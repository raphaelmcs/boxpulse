<?php

namespace App\Http\Controllers;

use App\Actions\PrepareWorkoutSession;
use App\Http\Requests\PrepareClassroomRequest;
use App\Models\Workout;
use App\Models\WorkoutSession;
use App\Workouts\ClassroomWorkouts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClassroomController extends Controller
{
    public function index(Request $request, ClassroomWorkouts $classes): View
    {
        Gate::authorize('viewAny', Workout::class);
        $box = $request->user()->box;
        $modalities = $box->modalities()->orderBy('training_modalities.id')->get();
        $filter = $request->validate(['modality' => ['nullable', Rule::in($modalities->pluck('slug')->all())]]);
        $ongoing = $classes->activeSessions()->filter(fn ($session) => $session->hasExecutionActivity());
        $query = $classes->today($box)->with('modality', 'blocks.stations', 'activeSession.timers', 'latestSession');
        if (! empty($filter['modality'])) {
            $query->where('training_modality_id', $modalities->firstWhere('slug', $filter['modality'])->id);
        }

        return view('classroom.today', [
            'box' => $box, 'today' => now($box->timezone), 'modalities' => $modalities,
            'workouts' => $query->orderBy('training_modality_id')->orderBy('id')->get(),
            'filter' => $filter['modality'] ?? '', 'ongoing' => $ongoing,
        ]);
    }

    public function history(Request $request, Workout $workout): View
    {
        Gate::authorize('view', $workout);

        return view('sessions.index', [
            'box' => $request->user()->box, 'workout' => $workout, 'classroom' => true,
            'sessions' => WorkoutSession::where('workout_id', $workout->id)->orderByDesc('id')->paginate(20),
        ]);
    }

    public function prepare(PrepareClassroomRequest $request, Workout $workout, PrepareWorkoutSession $prepare): RedirectResponse
    {
        $session = $prepare->handle($request->user(), $workout, $request->validated());

        return to_route('coach.ready', $session)->with('success', $session->hasExecutionActivity() ? 'Aula recuperada. O conteúdo e o tempo foram preservados.' : 'Aula preparada. O cronômetro aguarda o comando do coach.');
    }

    public function ready(Request $request, WorkoutSession $session, ClassroomWorkouts $classes): View
    {
        Gate::authorize('view', $session);

        return view('classroom.ready', ['box' => $request->user()->box, 'session' => $classes->snapshot($session)]);
    }
}
