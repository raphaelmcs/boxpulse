<?php

namespace App\Http\Controllers;

use App\Models\TrainingModality;
use App\Models\Workout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ModalityController extends Controller
{
    public function __invoke(Request $request, TrainingModality $modality): View
    {
        Gate::authorize('viewAny', Workout::class);

        abort_unless($request->user()->box->offersModality($modality->id), 404);

        return view('modalities.show', ['box' => $request->user()->box, 'modality' => $modality]);
    }
}
