<?php

namespace App\Http\Controllers;

use App\Actions\PrepareWorkoutSession;
use App\Http\Requests\PrepareClassroomRequest;
use App\Http\Requests\RestartWorkoutSessionRequest;
use App\Models\Workout;
use App\Models\WorkoutSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class WorkoutSessionController extends Controller
{
    public function restart(RestartWorkoutSessionRequest $request, WorkoutSession $session, PrepareWorkoutSession $prepare): RedirectResponse
    {
        $replacement = $prepare->handle($request->user(), $session->workout, $request->validated(), $session);

        return to_route('sessions.control', $replacement)->with('success', 'Nova sessão preparada com a versão publicada atual. A aula anterior permanece no histórico.');
    }

    public function store(PrepareClassroomRequest $request, Workout $workout, PrepareWorkoutSession $prepare): RedirectResponse
    {
        $options = $request->validated();
        $session = $prepare->handle($request->user(), $workout, $options);

        return to_route('sessions.control', $session);
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Workout::class);

        return view('sessions.index', ['box' => $request->user()->box, 'sessions' => WorkoutSession::orderByDesc('id')->paginate(20)]);
    }

    public function control(Request $request, WorkoutSession $session): View
    {
        Gate::authorize('view', $session);

        return view('sessions.control', ['box' => $request->user()->box, 'session' => $session, 'canControl' => $request->user()->can('control', $session)]);
    }

    public function tv(Request $request, WorkoutSession $session): View
    {
        Gate::authorize('view', $session);

        return view('sessions.tv', ['box' => $request->user()->box, 'session' => $session]);
    }

    public function summary(Request $request, WorkoutSession $session): View
    {
        Gate::authorize('view', $session);

        return view('sessions.summary', ['box' => $request->user()->box, 'session' => $session->load('timers')]);
    }
}
