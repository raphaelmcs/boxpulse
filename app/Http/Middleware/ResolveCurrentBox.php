<?php

namespace App\Http\Middleware;

use App\Tenancy\CurrentBox;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCurrentBox
{
    public function __construct(private CurrentBox $currentBox) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->currentBox->clear();
        abort_unless($request->user()?->box_id !== null, 403, 'Usuário sem box vinculada.');
        $this->currentBox->set((int) $request->user()->box_id);

        try {
            return $next($request);
        } finally {
            $this->currentBox->clear();
        }
    }
}
