<?php

namespace App\Http\Controllers;

use App\Actions\UpdateBoxSettings;
use App\Http\Requests\UpdateBoxSettingsRequest;
use App\Models\TrainingModality;
use App\Support\BoxTvLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BoxSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        abort_unless($request->user()->role === 'admin', 403);

        $box = $request->user()->box()->firstOrFail();

        return response()->view('settings.box', [
            'box' => $box,
            'timezones' => timezone_identifiers_list(),
            'modalities' => TrainingModality::orderBy('id')->get(),
            'tvLink' => BoxTvLink::url($box),
            'tvPresence' => BoxTvLink::presence($box),
        ], headers: ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']);
    }

    public function update(UpdateBoxSettingsRequest $request, UpdateBoxSettings $update): RedirectResponse
    {
        $update->handle($request->user(), $request->validated(), $request->file('logo'));

        return to_route('box.settings')->with('success', 'Identidade da box atualizada com sucesso.');
    }
}
