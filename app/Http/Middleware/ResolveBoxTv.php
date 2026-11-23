<?php

namespace App\Http\Middleware;

use App\Models\Box;
use App\Tenancy\CurrentBox;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveBoxTv
{
    public function __construct(private CurrentBox $currentBox) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->currentBox->clear();
        $token = $request->bearerToken();
        $request->headers->remove('Authorization');
        $request->server->remove('HTTP_AUTHORIZATION');
        $request->server->remove('REDIRECT_HTTP_AUTHORIZATION');
        if (! is_string($token) || ! preg_match('/\A[a-f0-9]{64}\z/', $token)) {
            return response()->json([], 404);
        }

        $digest = hash('sha256', $token);
        $box = Box::query()->where('tv_access_hash', $digest)->first(['id', 'name', 'timezone', 'primary_color', 'secondary_color', 'logo_path', 'tv_access_hash']);
        if (! $box || ! hash_equals($box->tv_access_hash, $digest)) {
            return response()->json([], 404);
        }

        $request->attributes->set(Box::class, $box);
        $this->currentBox->set($box->id);

        try {
            return $next($request);
        } finally {
            $this->currentBox->clear();
        }
    }
}
