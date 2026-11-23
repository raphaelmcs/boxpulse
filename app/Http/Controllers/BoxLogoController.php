<?php

namespace App\Http\Controllers;

use App\Support\BoxLogo;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BoxLogoController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        return BoxLogo::response($request->user()->box);
    }
}
