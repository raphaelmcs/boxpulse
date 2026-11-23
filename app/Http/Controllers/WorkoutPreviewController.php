<?php

namespace App\Http\Controllers;

use App\Models\Workout;
use App\Workouts\WorkoutDefinition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class WorkoutPreviewController extends Controller
{
    public function __invoke(Request $request, Workout $workout, WorkoutDefinition $definition): View
    {
        Gate::authorize('view', $workout);
        if ($request->routeIs('workouts.presentation')) {
            abort_unless($workout->status === 'published' && $request->user()->box->offersModality($workout->training_modality_id), 409, 'Publique o treino antes de preparar a apresentação.');
        }

        $draftPreview = $request->routeIs('workouts.preview') && $request->boolean('draft') && $workout->hasUnpublishedChanges();
        if ($draftPreview) {
            Gate::authorize('update', $workout);
        }

        $display = $draftPreview || $workout->status === 'draft'
            ? $definition->draftPreview($workout)
            : $workout->load('modality', 'blocks.stations');

        return view('workouts.preview', ['box' => $request->user()->box, 'workout' => $display, 'draftPreview' => $draftPreview]);

    }
}
