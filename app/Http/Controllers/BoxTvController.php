<?php

namespace App\Http\Controllers;

use App\Actions\ReadBoxTvState;
use App\Http\Resources\SessionPresentationResource;
use App\Models\Box;
use App\Support\BoxLogo;
use App\Support\BoxTheme;
use App\Support\BoxTvLink;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class BoxTvController extends Controller
{
    public function open(): View
    {
        return view('sessions.box-tv');
    }

    public function state(Request $request, ReadBoxTvState $read): Response
    {
        $box = $request->attributes->get(Box::class);
        $now = CarbonImmutable::now('UTC');
        $session = $read->handle($now);
        $status = match (true) {
            $session?->status === 'active' => $session->timers->firstWhere('position', $session->current_position) ? 'active' : 'unavailable',
            (bool) $session?->finished_at?->greaterThan($now->subSeconds(config('boxpulse.tv_end_seconds'))) => 'ended',
            default => 'waiting',
        };
        $modalities = $status === 'waiting' ? $box->modalities()->limit(2)->pluck('training_modalities.name') : collect();
        $identity = [
            'name' => $box->name,
            'timezone' => $box->timezone,
            'theme' => BoxTheme::variables($box),
            'logo_version' => $box->logo_path ? hash('sha256', $box->logo_path) : null,
            'modality' => $status === 'active' ? $session->modality_name : ($modalities->count() === 1 ? $modalities->first() : null),
        ];
        $tag = hash('sha256', json_encode([$status, $identity, $session?->id, $session?->version], JSON_THROW_ON_ERROR));
        if (in_array('"'.$tag.'"', $request->getETags(), true)) {
            $response = response()->noContent(304);
        } else {
            $response = response()->json([
                'status' => $status,
                'box' => $identity,
                'data' => $status === 'active' ? (new SessionPresentationResource($session))->resolve($request) : null,
            ]);
        }
        BoxTvLink::heartbeat($box);
        $response->headers->set('X-Server-Time-Ms', (string) CarbonImmutable::now('UTC')->getTimestampMs());
        $response->setEtag($tag);
        $response->setVary('Authorization');

        return $response;
    }

    public function logo(Request $request): BinaryFileResponse
    {
        return BoxLogo::response($request->attributes->get(Box::class));
    }
}
