<?php

namespace App\Workouts;

use App\Models\WorkoutBlock;
use App\Timers\CompileTimer;

class WorkoutPresentation
{
    public static function duration(?int $milliseconds): string
    {
        if ($milliseconds === null) {
            return 'Sem limite de tempo';
        }
        $seconds = intdiv($milliseconds, 1000);
        $minutes = intdiv($seconds, 60);
        $remainder = $seconds % 60;

        return ($minutes ? $minutes.' min' : '').($minutes && $remainder ? ' ' : '').($remainder || ! $minutes ? $remainder.' s' : '');
    }

    public static function mainTiming(?WorkoutBlock $block): string
    {
        if (! $block) {
            return 'Bloco principal não informado';
        }
        $config = app(CompileTimer::class)->compile([
            'timer_type' => $block->timer_type, 'duration_seconds' => $block->duration_seconds,
            'timer_settings' => $block->timer_settings ?? [], 'stations' => $block->stations->toArray(),
        ]);
        $label = TimerConfiguration::LABELS[$block->timer_type];
        if ($config['direction'] === 'none') {
            return $label;
        }

        return $label.' · '.($block->timer_type === 'for_time' && $config['duration_ms'] !== null ? 'TC ' : '').self::duration($config['duration_ms']);
    }
}
