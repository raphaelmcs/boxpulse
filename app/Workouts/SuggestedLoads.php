<?php

namespace App\Workouts;

class SuggestedLoads
{
    public const LEVELS = ['rx' => 'RX', 'intermediate' => 'Intermediate', 'scale' => 'Scale'];

    public static function normalize(?array $loads): ?array
    {
        $result = [];
        foreach (['equipment', 'rx', 'intermediate', 'scale', 'unit'] as $field) {
            $value = trim($loads[$field] ?? '');
            $result[$field] = $value === '' ? null : $value;
        }
        if (! array_filter(array_intersect_key($result, self::LEVELS), fn ($value) => $value !== null)) {
            return null;
        }

        return $result;
    }

    public static function display(string $value, ?string $unit): string
    {
        return $unit && preg_match('/\A[0-9]+(?:[.,][0-9]+)?\z/', $value) ? $value.' '.$unit : $value;
    }
}
