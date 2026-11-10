<?php

namespace App\Http\Controllers;

use App\Models\Workout;
use App\Workouts\ClassroomWorkouts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('viewAny', Workout::class);
        $box = $request->user()->box;
        $today = now($box->timezone);

        return view('dashboard', [
            'box' => $box,
            'today' => $today,
            'modalities' => $box->modalities()->orderBy('training_modalities.id')->get(),
            'workouts' => app(ClassroomWorkouts::class)->today($box)->with('modality')->orderBy('id')->get(),
            'drafts' => Workout::with('modality')->whereIn('training_modality_id', $box->modalities()->select('training_modalities.id'))->whereDate('scheduled_for', $today->toDateString())->where('status', 'draft')->orderBy('id')->get(),
            'upcoming' => Workout::with('modality')->whereIn('training_modality_id', $box->modalities()->select('training_modalities.id'))->where('scheduled_for', '>', $today->toDateString())->orderBy('scheduled_for')->orderBy('id')->limit(6)->get(),
        ]);
    }
}
