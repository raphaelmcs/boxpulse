<?php

namespace App\Workouts;

class SuggestedLoads
{
    public const LEVELS = ['rx' => 'RX', 'intermediate' => 'Intermediate', 'scale' => 'Scale'];

    public const IMPLEMENTS = ['single' => 'Individual', 'double' => 'Duplo · por unidade', 'total' => 'Carga total'];

    public const MAX_ENTRIES = 12;

    public static function normalize(?array $loads): ?array
    {
        $values = self::fields($loads);

        return self::hasLevels($values) ? $values : null;
    }

    public static function entryRules(): array
    {
        $prefix = 'blocks.*.load_entries';
        $entry = "$prefix.*";
        $plainText = fn (int $limit) => ['nullable', 'string', "max:$limit", 'regex:/\A[^\x00-\x1F\x7F]*\z/u'];

        return [
            $prefix => ['nullable', 'array', 'list', 'max:'.self::MAX_ENTRIES],
            $entry => ['required', 'array:equipment,movement,rx,intermediate,scale,unit,source,reference,implement,preset_key'],
            "$entry.equipment" => $plainText(80),
            "$entry.movement" => $plainText(120),
            "$entry.reference" => $plainText(160),
            "$entry.unit" => ['nullable', 'in:kg,lb'],
            "$entry.source" => ['nullable', 'in:manual,preset,explicit'],
            "$entry.implement" => ['nullable', 'in:single,double,total'],
            "$entry.preset_key" => ['nullable', 'string', 'max:120', 'regex:/\A(?:box:[0-9]+|base:[a-z0-9-]+)\z/'],
            ...collect(self::LEVELS)->mapWithKeys(fn ($label, $key) => ["$entry.$key" => $plainText(60)])->all(),
        ];
    }

    public static function normalizeEntries(array $entries): ?array
    {
        $normalized = [];
        foreach ($entries as $entry) {
            $values = self::fields($entry);
            if (self::hasLevels($values) || $values['equipment'] !== null) {
                $normalized[] = [
                    ...$values,
                    'movement' => $entry['movement'] ?? null,
                    'source' => $entry['source'] ?? 'manual',
                    'reference' => $entry['reference'] ?? null,
                    ...array_filter(array_intersect_key($entry, array_flip(['implement', 'preset_key']))),
                ];
            }
        }

        return $normalized ?: null;
    }

    private static function fields(?array $loads): array
    {
        $fields = ['equipment', ...array_keys(self::LEVELS), 'unit'];
        $values = [];
        foreach ($fields as $field) {
            $value = trim($loads[$field] ?? '');
            $values[$field] = $value === '' ? null : $value;
        }

        return $values;
    }

    private static function hasLevels(array $values): bool
    {
        return array_filter(array_intersect_key($values, self::LEVELS), fn ($value) => $value !== null) !== [];
    }

    public static function display(string $value, ?string $unit): string
    {
        return $unit && preg_match('/\A[0-9]+(?:[.,][0-9]+)?\z/', $value) ? $value.' '.$unit : $value;
    }
}
