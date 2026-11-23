<?php

namespace App\Workouts;

use Illuminate\Support\Str;

class WorkoutLoadAnalyzer
{
    private const EQUIPMENT = [
        'Dumbbell' => 'dumbbells?|db',
        'Kettlebell' => 'kettlebells?|kb',
        'Barbell' => 'barbells?|bb',
        'Wall Ball' => 'wall[ -]*balls?|wb',
        'Sandbag' => 'sand[ -]*bags?',
        'Sled' => 'sleds?',
        'Medicine Ball' => 'medicine[ -]+balls?',
        'Plate' => 'plates?',
    ];

    public function __construct(private LoadLibrary $library, private MovementCatalog $catalog = new MovementCatalog) {}

    /** @return list<array<string, mixed>> */
    public function analyze(array $block, array $presets, ?array $movements = null): array
    {
        $patterns = [];
        foreach ($movements ?? $this->catalog->base() as $movement) {
            if ($movement['active']) {
                $patterns[] = [$movement, '/(?<![a-z0-9_])(?:'.$this->catalog->pattern($movement).')(?![a-z0-9_])/'];
            }
        }
        $context = $this->context(implode("\n", [$block['title'] ?? '', $block['description'] ?? '', $block['notes'] ?? '']))
            ?? ($block['type'] === 'skill' ? 'technique' : null);
        $results = [];
        foreach (['title', 'description', 'notes'] as $field) {
            foreach (WorkoutTextStyle::lines($block[$field] ?? null) as $index => $line) {
                array_push($results, ...$this->line($line, $field, $index + 1, $patterns, $context, $block['modality'] ?? null));
            }
        }
        foreach ($block['stations'] ?? [] as $index => $station) {
            $named = $this->line($station['name'] ?? '', 'station', $index + 1, $patterns, $context, $block['modality'] ?? null);
            if (count($named) === 1 && $named[0]['explicit'] === null && ! $named[0]['review'] && isset($station['load'])) {
                $named[0]['explicit'] = ['male' => (string) $station['load'], 'female' => null, 'unit' => 'kg'];
            }
            array_push($results, ...$named);
            foreach (WorkoutTextStyle::lines($station['description'] ?? null) as $line) {
                array_push($results, ...$this->line($line, 'station', $index + 1, $patterns, $context, $block['modality'] ?? null));
            }
        }

        return array_map(fn (array $result) => [
            ...$result,
            'block' => $block['type'],
            'presets' => $result['movement_review'] ? [] : $this->library->matching($presets, $result['equipment'], $result['movement'], $result['context']),
        ], array_slice(array_values(array_unique($results, SORT_REGULAR)), 0, 48));
    }

    private function line(string $text, string $field, int $line, array $patterns, ?string $context, ?string $modality): array
    {
        $normalized = Str::lower(Str::ascii(str_replace('×', 'x', $text)));
        $context = $this->context($normalized) ?? $context;
        $matches = [];
        foreach ($patterns as [$movement, $pattern]) {
            preg_match_all($pattern, $normalized, $found, PREG_OFFSET_CAPTURE);
            foreach ($found[0] as [$alias, $offset]) {
                $matches[] = ['start' => $offset, 'end' => $offset + strlen($alias), 'movement' => $movement, 'ambiguous' => false];
            }
        }
        usort($matches, fn (array $left, array $right): int => ($left['start'] <=> $right['start']) ?: ($right['end'] <=> $left['end']));
        $selected = [];
        foreach ($matches as $match) {
            $last = array_key_last($selected);
            $previous = $last === null ? null : $selected[$last];
            if ($previous === null || $previous['end'] <= $match['start']) {
                $selected[] = $match;

                continue;
            }
            if ($previous['start'] === $match['start'] && $previous['end'] === $match['end']) {
                $same = $previous['movement']['normalized_name'] === $match['movement']['normalized_name']
                    && LoadLibrary::key($previous['movement']['equipment']) === LoadLibrary::key($match['movement']['equipment']);
                if (! $same) {
                    $selected[$last]['ambiguous'] = true;
                } elseif ($match['movement']['origin'] === 'box') {
                    $selected[$last]['movement'] = $match['movement'];
                }
            }
        }

        $results = [];
        if ($selected === []) {
            foreach (self::EQUIPMENT as $equipment => $pattern) {
                if (preg_match('/\b(?:'.$pattern.')\b/', $normalized)) {
                    $results[] = $this->result($text, $field, $line, $equipment, null, $normalized, $normalized, $context, true);
                }
            }

            return $results;
        }

        foreach ($selected as $index => $match) {
            $movement = $match['movement'];
            $equipment = $movement['equipment'];
            $next = $selected[$index + 1]['start'] ?? strlen($normalized);
            $previousEnd = $selected[$index - 1]['end'] ?? 0;
            $before = substr($normalized, $previousEnd, $match['start'] - $previousEnd);
            // The following movement may start after its rep count or implement qualifier.
            $after = substr($normalized, $match['end'], $next - $match['end']);
            $review = $match['ambiguous'] || ($modality && ! in_array($modality, $movement['modalities'], true));
            foreach (self::EQUIPMENT as $name => $pattern) {
                if (preg_match('/\b(?:'.$pattern.')\s*$/', $before)
                    || (! isset($selected[$index + 1]) && preg_match('/^\s+(?:com\s+|with\s+)?(?:'.$pattern.')\b/', $after))) {
                    $review = $review || $equipment !== $name;
                    $equipment = $name;
                    break;
                }
            }
            $phrase = $before.substr($normalized, $match['start'], $match['end'] - $match['start']).$after;
            $results[] = $this->result($text, $field, $line, $equipment, $movement, $after, $phrase, $context, $review);
        }

        return $results;
    }

    private function context(string $text): ?string
    {
        $text = LoadLibrary::key($text);

        return match (true) {
            preg_match('/\b(?:tecnica|technique|skill)\b/', $text) === 1 => 'technique',
            preg_match('/\b(?:forca|strength)\b/', $text) === 1 => 'strength',
            preg_match('/\b(?:condicionamento|conditioning|metcon|amrap)\b/', $text) === 1 => 'conditioning',
            default => null,
        };
    }

    private function result(string $text, string $field, int $line, string $equipment, ?array $movement, string $segment, string $phrase, ?string $context, bool $movementReview): array
    {
        $number = '[0-9]{1,6}(?:[.,][0-9]{1,3})?';
        $implement = match (true) {
            preg_match('/\b(?:total|carga total)\b/', $phrase) === 1 => 'total',
            preg_match('/\b(?:double|duplo|dois\s+(?:db|kb)|2\s*(?:db|kb))\b/', $phrase) === 1
                || preg_match('/\b2\s*x\s*'.$number.'(?:\s*\/\s*'.$number.')?\s*(?:kg|lbs?)\b/', $phrase) === 1 => 'double',
            preg_match('/\b(?:single|individual|unilateral|1\s*(?:db|kb))\b/', $phrase) === 1 => 'single',
            default => null,
        };
        $segment = preg_replace('/\b[12]\s*x\s*(?='.$number.'(?:\s*\/\s*'.$number.')?\s*(?:kg|lbs?)\b)/', '', $segment);
        $count = preg_match_all('/(?<![a-z0-9.,\/\-])('.$number.')(?:\s*\/\s*('.$number.'))?\s*(kg|lbs?)\b/i', $segment, $matches, PREG_SET_ORDER);
        $relative = preg_match('/('.$number.')\s*%/', $segment, $percent) ? $percent[1].'%' : null;
        $hasUnit = preg_match('/\b(?:kg|lbs?)\b|\d(?:kg|lbs?)\b/i', $segment) === 1;
        $ambiguous = $count > 1 || ($hasUnit && $count !== 1) || ($count === 1 && preg_match('/(?:\d\s*(?:[-–—]|\ba\b|\bto\b)\s*\d|\d\s*%.*[0-9]\s*(?:kg|lbs?)\b|\b1\s*rm\b.*[0-9]\s*(?:kg|lbs?)\b)/i', $segment));
        $explicit = $count === 1 && ! $ambiguous && ! $movementReview
            ? ['male' => $matches[0][1], 'female' => $matches[0][2] !== '' ? $matches[0][2] : null, 'unit' => $matches[0][3] === 'kg' ? 'kg' : 'lb']
            : null;

        return ['text' => $text, 'field' => $field, 'line' => $line, 'equipment' => $equipment, 'movement' => $movement['name'] ?? null,
            'variation' => $movement['variation'] ?? null, 'context' => $context, 'implement' => $implement,
            'explicit' => $explicit, 'relative' => $relative, 'movement_review' => $movementReview, 'review' => $movementReview || $ambiguous];
    }
}
