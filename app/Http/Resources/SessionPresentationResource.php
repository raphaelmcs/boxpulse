<?php

namespace App\Http\Resources;

use App\Models\TimerSession;
use App\Workouts\TimerConfiguration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SessionPresentationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'timer_formats' => TimerConfiguration::LABELS,
            'id' => $this->id,
            'title' => $this->title,
            'modality_name' => $this->modality_name,
            'status' => $this->status,
            'current_position' => $this->current_position,
            'version' => $this->version,
            'timers' => $this->timers->map($this->timerArray(...))->values()->all(),
        ];
    }

    protected function timerArray(TimerSession $timer): array
    {
        return [
            'id' => $timer->id, 'position' => $timer->position, 'block_type' => $timer->block_type,
            'title' => $timer->title, 'description' => $timer->description, 'notes' => $timer->notes,
            'suggested_loads' => $timer->suggested_loads, 'load_entries' => $timer->load_entries, 'text_style' => $timer->text_style,
            'timer_type' => $timer->timer_type, 'config' => $timer->config, 'stations' => $timer->stations_snapshot,
            'state' => $timer->state, 'elapsed_ms' => $timer->elapsed_ms,
            'effective_started_at_ms' => $timer->effective_started_at ? (int) $timer->effective_started_at->getTimestampMs() : null,
        ];
    }
}
