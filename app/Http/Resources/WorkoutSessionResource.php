<?php

namespace App\Http\Resources;

use App\Models\TimerSession;
use App\Support\BoxTheme;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Attributes\PreserveKeys;

#[PreserveKeys]
class WorkoutSessionResource extends SessionPresentationResource
{
    public function toArray(Request $request): array
    {
        $box = $request->user()->box;
        $source = $this->workout;
        $enabled = $box->offersModality($source->training_modality_id);

        return [
            ...parent::toArray($request),
            'box' => ['name' => $box->name, 'timezone' => $box->timezone, 'theme' => BoxTheme::variables($box), 'logo_url' => $box->logo_path ? route('box.logo', ['v' => hash('sha256', $box->logo_path)]) : null],
            'execution_started' => $this->hasExecutionActivity(),
            'source' => [
                'version' => $source->content_version, 'session_version' => $this->workout_version,
                'updated' => $this->workout_version !== $source->content_version,
                'modality_enabled' => $enabled,
                'published' => $source->status === 'published' && $enabled, 'title' => $source->title,
                'preview_url' => route('workouts.preview', $source),
                'restart_url' => route('sessions.restart', $this->resource),
                'replacement_url' => $this->superseded_by_id ? route('sessions.control', $this->superseded_by_id) : null,
            ],
            'scheduled_for' => $this->scheduled_for->format('Y-m-d'), 'auto_advance' => $this->auto_advance,
            'server_time_ms' => (int) now('UTC')->getTimestampMs(), 'started_at' => $this->started_at?->toISOString(), 'finished_at' => $this->finished_at?->toISOString(),
            'can_control' => $request->user()->can('control', $this->resource),
            'controller_client_id' => $this->controller_client_id, 'controller_lease_expires_at_ms' => $this->controller_lease_expires_at ? (int) $this->controller_lease_expires_at->getTimestampMs() : null,
        ];
    }

    protected function timerArray(TimerSession $timer): array
    {
        return [
            ...parent::timerArray($timer),
            'started_at' => $timer->started_at?->toISOString(), 'paused_at' => $timer->paused_at?->toISOString(), 'finished_at' => $timer->finished_at?->toISOString(),
        ];
    }
}
