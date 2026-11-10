<?php

namespace App\Http\Controllers;

use App\Models\Workout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('viewAny', Workout::class);
        $box = $request->user()->box;

        return view('workspace', ['box' => $box, 'today' => now($box->timezone)]);
    }
}
