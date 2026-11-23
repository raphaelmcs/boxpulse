<?php

namespace App\Http\Controllers;

use App\Actions\RotateBoxTvLink;
use App\Support\BoxTvLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BoxTvSettingsController extends Controller
{
    public function rotate(Request $request, RotateBoxTvLink $rotate): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);
        $data = $request->validate(['confirmation' => ['sometimes', 'accepted']]);
        $rotate->handle($request->user(), isset($data['confirmation']));

        return to_route('box.settings')->with('success', 'Link da TV atualizado.');
    }

    public function presence(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === 'admin', 403);

        $box = $request->user()->box()->firstOrFail();

        return response()->json(BoxTvLink::presence($box))->header('Cache-Control', 'private, no-store');
    }
}
