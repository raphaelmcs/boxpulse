<?php

namespace App\Timers;

class CompileTimer
{
    public function compile(array $block, ?int $preparation = null): array
    {
        $type = $block['timer_type'];
        $settings = $block['timer_settings'] ?? [];
        $duration = isset($block['duration_seconds']) ? (int) $block['duration_seconds'] * 1000 : null;
        $free = in_array($type, ['none', 'repetitions'], true);
        $config = [
            'version' => 1, 'type' => $type,
            'preparation_ms' => $free ? 0 : ($preparation ?? (int) ($settings['preparation_seconds'] ?? 10)) * 1000,
            'duration_ms' => $duration,
            'direction' => $free ? 'none' : (in_array($type, ['countdown', 'amrap', 'emom', 'tabata', 'interval', 'circuit'], true) ? 'down' : 'up'),
            'limit_ms' => $free || ($type === 'for_time' && ! ($settings['stop_at_cap'] ?? true)) ? null : $duration,
            'interval_ms' => (int) ($settings['interval_seconds'] ?? 60) * 1000,
            'rounds' => (int) ($settings['rounds'] ?? 1), 'cycle_ms' => 0, 'segments' => [],
        ];
        if (! in_array($type, ['tabata', 'interval', 'circuit'], true)) {
            return $config;
        }
        $stations = $type === 'circuit' ? $block['stations'] : [[]];
        $tail = 0;
        foreach ($stations as $index => $station) {
            $durations = [
                'work' => (int) ($station['duration_seconds'] ?? $settings['work_seconds']) * 1000,
                'rest' => (int) ($station['rest_seconds'] ?? $settings['rest_seconds']) * 1000,
                'transition' => $type === 'tabata' ? 0 : (int) ($station['transition_seconds'] ?? $settings['transition_seconds'] ?? 0) * 1000,
            ];
            foreach ($durations as $phase => $milliseconds) {
                if ($milliseconds > 0) {
                    $config['segments'][] = ['phase' => $phase, 'duration_ms' => $milliseconds, 'station' => $type === 'circuit' ? $index : null];
                    $config['cycle_ms'] += $milliseconds;
                }
            }
            $tail = $durations['rest'] + $durations['transition'];
        }
        $config['limit_ms'] = $config['cycle_ms'] * $config['rounds'] - $tail;
        $config['duration_ms'] = $config['limit_ms'];

        return $config;
    }
}
