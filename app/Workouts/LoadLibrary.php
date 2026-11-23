<?php

namespace App\Workouts;

use App\Models\LoadPreset;
use Illuminate\Support\Str;

class LoadLibrary
{
    public const EQUIPMENT = ['Barbell', 'Dumbbell', 'Kettlebell', 'Wall Ball', 'Sandbag', 'Sled', 'Medicine Ball', 'Plate', 'Outros'];

    public const CONTEXTS = ['technique' => 'Técnica', 'strength' => 'Força', 'conditioning' => 'Condicionamento'];

    public function presets(bool $includeInactive = false): array
    {
        $boxPresets = LoadPreset::query()->with('catalogMovement')
            ->when(! $includeInactive, fn ($query) => $query->where('active', true)
                ->where(fn ($query) => $query->whereNull('movement_id')->orWhereHas('catalogMovement', fn ($query) => $query->where('active', true))))
            ->orderBy('equipment')->orderBy('movement')->orderBy('id')->get()
            ->map(fn (LoadPreset $preset) => $this->serialize($preset))->all();

        return [...$boxPresets, ...$this->basePresets()];
    }

    public function serialize(LoadPreset $preset): array
    {
        $movement = $preset->catalogMovement;

        return [
            'id' => $preset->id, 'key' => 'box:'.$preset->id, 'origin' => 'box',
            ...$preset->only(['context', 'notes', 'movement_id', 'levels', 'unit', 'reference', 'active']),
            'name' => $preset->name ?? $preset->reference ?? $movement?->name ?? $preset->movement ?? $preset->equipment,
            'equipment' => $movement?->equipment ?? $preset->equipment,
            'movement' => $movement?->name ?? $preset->movement,
            'movement_active' => $movement?->active ?? true,
        ];
    }

    public function matching(array $presets, string $equipment, ?string $movement, ?string $context = null): array
    {
        if ($movement === null) {
            return [];
        }

        $matches = array_filter($presets, fn (array $preset): bool => $preset['active'] && ($preset['movement_active'] ?? true)
            && array_filter($preset['levels'], fn (array $pair): bool => array_filter($pair, fn ($weight) => $weight !== null && $weight !== '') !== [])
            && self::key($preset['equipment']) === self::key($equipment)
            && self::key($preset['movement'] ?? '') === self::key($movement)
            && (($preset['context'] ?? null) === null || $preset['context'] === $context));
        usort($matches, fn (array $left, array $right): int => $this->priority($left, $context) <=> $this->priority($right, $context));

        return $matches;
    }

    private function priority(array $preset, ?string $context): int
    {
        return match (true) {
            $preset['origin'] === 'base' => 2,
            $context !== null && ($preset['context'] ?? null) === $context => 0,
            default => 1,
        };
    }

    public static function key(string $text): string
    {
        return Str::lower(Str::ascii(Str::squish($text)));
    }

    private function basePresets(): array
    {
        $base = [
            ['Dumbbell', 'Dumbbell Snatch', 'CrossFit Open 24.1 · categoria 16–54', ['rx' => [22.5, 15], 'scale' => [15, 10]]],
            ['Wall Ball', 'Wall Ball', 'CrossFit Open 26.1 · categoria 16–54', ['rx' => [9, 6], 'scale' => [6, 4]]],
            ['Barbell', 'Thruster', 'CrossFit Open 25.2 · 1ª carga (RX, Scaled, Foundations)', ['rx' => [43, 29], 'intermediate' => [29, 20], 'scale' => [20, 15]]],
        ];

        return array_map(fn (array $row) => [
            'id' => null, 'key' => 'base:'.Str::slug($row[1]), 'origin' => 'base',
            'equipment' => $row[0], 'movement' => $row[1], 'reference' => $row[2], 'name' => $row[2],
            'context' => null, 'notes' => null, 'movement_id' => null, 'movement_active' => true,
            'unit' => 'kg', 'active' => true,
            'levels' => array_map(fn (array $pair) => array_combine(['male', 'female'], $pair), $row[3]),
        ], $base);
    }
}
