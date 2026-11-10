<?php

namespace App\Workouts;

class WorkoutDurationInput
{
    public function usesMinutes(array $block): bool
    {
        return array_key_exists('duration_minutes', $block) || array_key_exists('duration_extra_seconds', $block);
    }

    public function seconds(mixed $minutes, mixed $extra): ?int
    {
        if (($minutes === null || $minutes === '') && ($extra === null || $extra === '' || $extra === 0 || $extra === '0')) {
            return null;
        }
        $minutes ??= 0;
        $extra ??= 0;
        $minutes = $minutes === '' ? 0 : $minutes;
        $extra = $extra === '' ? 0 : $extra;
        if (! is_scalar($minutes) || ! is_scalar($extra)
            || filter_var($minutes, FILTER_VALIDATE_INT) === false || filter_var($extra, FILTER_VALIDATE_INT) === false
            || (int) $minutes < 0 || (int) $minutes > 1440 || (int) $extra < 0 || (int) $extra > 59) {
            return null;
        }

        return (int) $minutes * 60 + (int) $extra;
    }

    public function editor(array $block): array
    {
        if ($this->usesMinutes($block)) {
            return ['duration_minutes' => '', 'duration_extra_seconds' => 0, ...$block];
        }
        $seconds = $block['duration_seconds'] ?? null;

        return [...$block, 'duration_minutes' => $seconds === null || $seconds === '' ? '' : intdiv((int) $seconds, 60), 'duration_extra_seconds' => $seconds === null || $seconds === '' ? 0 : (int) $seconds % 60];
    }

    public function canonical(array $data): array
    {
        foreach (array_keys($data['blocks'] ?? []) as $index) {
            unset($data['blocks'][$index]['duration_minutes'], $data['blocks'][$index]['duration_extra_seconds']);
        }

        return $data;
    }
}
