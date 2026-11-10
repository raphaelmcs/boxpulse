<?php

namespace App\Http\Controllers;

use App\Actions\UpdateBoxSettings;
use App\Http\Requests\UpdateBoxSettingsRequest;
use App\Models\TrainingModality;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BoxSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        abort_unless($request->user()->role === 'admin', 403);

        return view('settings.box', ['box' => $request->user()->box, 'timezones' => timezone_identifiers_list(), 'modalities' => TrainingModality::orderBy('id')->get()]);
    }

    public function update(UpdateBoxSettingsRequest $request, UpdateBoxSettings $update): RedirectResponse
    {
        $update->handle($request->user(), $request->validated(), $request->file('logo'));

        return to_route('box.settings')->with('success', 'Identidade da box atualizada com sucesso.');
    }
}
