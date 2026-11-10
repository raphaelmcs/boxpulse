<?php

namespace App\Http\Controllers\Auth;

use App\Actions\RegisterBox;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterBoxRequest;
use App\Models\TrainingModality;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisteredBoxController extends Controller
{
    public function create(): View
    {
        return view('auth.register', ['timezones' => timezone_identifiers_list(), 'modalities' => TrainingModality::orderBy('id')->get()]);
    }

    public function store(RegisterBoxRequest $request, RegisterBox $register): RedirectResponse
    {
        $user = $register->handle($request->validated(), $request->file('logo'));
        Auth::login($user);
        $request->session()->regenerate();

        return to_route('workspace');
    }
}
