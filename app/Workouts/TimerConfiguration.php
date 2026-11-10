<?php

namespace App\Workouts;

class TimerConfiguration
{
    public const LABELS = [
        'none' => 'Sem tempo', 'stopwatch' => 'Contagem progressiva', 'countdown' => 'Contagem regressiva',
        'for_time' => 'FOR TIME', 'amrap' => 'AMRAP', 'emom' => 'EMOM', 'tabata' => 'Tabata',
        'interval' => 'Intervalado', 'circuit' => 'Circuito por estações', 'repetitions' => 'Circuito por repetições',
    ];

    public static function formats(string $modality, string $block): array
    {
        if ($block !== 'workout') {
            return array_intersect_key(self::LABELS, array_flip(['none', 'stopwatch', 'countdown', 'emom', 'tabata']));
        }
        $keys = match ($modality) {
            'crossfit' => ['for_time', 'amrap', 'emom'],
            'hyrox' => ['for_time', 'interval', 'amrap', 'emom'],
            default => ['circuit', 'repetitions', 'amrap', 'emom', 'tabata', 'interval', 'none'],
        };

        return array_intersect_key(self::LABELS, array_flip($keys));
    }

    public static function settingKeys(string $type): array
    {
        return match ($type) {
            'for_time' => ['stop_at_cap'],
            'emom' => ['interval_seconds'],
            'tabata' => ['work_seconds', 'rest_seconds', 'rounds'],
            'interval', 'circuit' => ['work_seconds', 'rest_seconds', 'rounds', 'transition_seconds'],
            'repetitions' => ['rounds'],
            default => [],
        };
    }

    public static function normalize(string $type, array $settings): array
    {
        $result = ['version' => 1, 'preparation_seconds' => (int) ($settings['preparation_seconds'] ?? 10)];
        foreach (self::settingKeys($type) as $key) {
            if (isset($settings[$key])) {
                $result[$key] = $key === 'stop_at_cap' ? (bool) $settings[$key] : (int) $settings[$key];
            }
        }

        return $result;
    }
}
