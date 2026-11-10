<?php

namespace App\Http\Controllers;

use App\Actions\ControlWorkoutSession;
use App\Http\Requests\SessionCommandRequest;
use App\Http\Resources\WorkoutSessionResource;
use App\Models\WorkoutSession;
use App\Timers\SessionState;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SessionStateController extends Controller
{
    public function show(Request $request, WorkoutSession $session, SessionState $state): JsonResponse
    {
        Gate::authorize('view', $session);
        $snapshot = DB::transaction(function () use ($session, $state): WorkoutSession {
            $locked = WorkoutSession::whereKey($session->id)->lockForUpdate()->firstOrFail();

            return $state->synchronize($locked, CarbonImmutable::now('UTC'));
        }, 3);

        return response()->json(['data' => (new WorkoutSessionResource($snapshot))->resolve($request)])->header('Cache-Control', 'private, no-store');
    }

    public function claim(Request $request, WorkoutSession $session, ControlWorkoutSession $control): JsonResponse
    {
        Gate::authorize('control', $session);
        $data = $request->validate(['client_id' => ['required', 'uuid'], 'takeover' => ['sometimes', 'boolean']]);
        $result = $control->claim($request->user(), $session, $data['client_id'], (bool) ($data['takeover'] ?? false));

        return $this->respond($request, $result);
    }

    public function command(SessionCommandRequest $request, WorkoutSession $session, ControlWorkoutSession $control): JsonResponse
    {
        return $this->respond($request, $control->handle($request->user(), $session, $request->validated()));
    }

    private function respond(Request $request, array $result): JsonResponse
    {
        return response()->json(['message' => $result['conflict'], 'data' => (new WorkoutSessionResource($result['session']))->resolve($request)], $result['conflict'] ? 409 : 200)->header('Cache-Control', 'private, no-store');
    }
}
