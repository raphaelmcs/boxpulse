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
        return self::LABELS;
    }

    public static function defaults(): array
    {
        return ['preparation_seconds' => 10, 'interval_seconds' => 60];
    }

    public static function parameterRules(): array
    {
        return [
            'stop_at_cap' => ['sometimes', 'boolean'],
            'preparation_seconds' => ['nullable', 'integer', 'min:0', 'max:60'],
            'interval_seconds' => ['nullable', 'integer', 'min:1', 'max:86400'],
            'work_seconds' => ['nullable', 'integer', 'min:1', 'max:86400'],
            'rest_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'rounds' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'transition_seconds' => ['nullable', 'integer', 'min:0', 'max:3600'],
        ];
    }

    public static function registry(): array
    {
        return collect(self::LABELS)->mapWithKeys(fn ($label, $type) => [$type => [
            'validation' => array_intersect_key(self::parameterRules(), array_flip(['preparation_seconds', ...self::settingKeys($type)])),
            'label' => $label, 'fields' => self::settingKeys($type),
            'defaults' => self::defaults(),
            'duration_required' => in_array($type, ['countdown', 'amrap', 'emom'], true),
            'duration_allowed' => in_array($type, ['stopwatch', 'countdown', 'for_time', 'amrap', 'emom'], true),
        ]])->all();
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
        $result = ['version' => 1, 'preparation_seconds' => (int) ($settings['preparation_seconds'] ?? self::defaults()['preparation_seconds'])];
        foreach (self::settingKeys($type) as $key) {
            $value = $settings[$key] ?? self::defaults()[$key] ?? null;
            if ($value !== null) {
                $result[$key] = $key === 'stop_at_cap' ? (bool) $value : (int) $value;
            }
        }

        return $result;
    }
}
