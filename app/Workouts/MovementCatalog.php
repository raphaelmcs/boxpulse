<?php

namespace App\Workouts;

use App\Models\LoadMovement;
use Illuminate\Support\Str;

class MovementCatalog
{
    /** @var array<string, list<array{0: string, 1: string, 2?: string}>> */
    private const BASE = [
        'Barbell' => [
            ['Snatch', 'snatch(?:es)?'],
            ['Power Snatch', 'power[ -]+snatch(?:es)?', 'Power'],
            ['Squat Snatch', 'squat[ -]+snatch(?:es)?', 'Squat'],
            ['Hang Snatch', 'hang[ -]+snatch(?:es)?', 'Hang'],
            ['Clean', 'cleans?'],
            ['Power Clean', 'power[ -]+cleans?', 'Power'],
            ['Squat Clean', 'squat[ -]+cleans?', 'Squat'],
            ['Hang Clean', 'hang[ -]+cleans?', 'Hang'],
            ['Clean & Jerk', 'cleans?\s*(?:&|\+|and|e)\s*jerks?|c\s*&\s*j'],
            ['Push Jerk', 'push[ -]+jerks?', 'Push'],
            ['Split Jerk', 'split[ -]+jerks?', 'Split'],
            ['Push Press', 'push[ -]+press(?:es)?', 'Push'],
            ['Strict Press', '(?:strict|shoulder)[ -]+press(?:es)?', 'Strict'],
            ['Shoulder to Overhead', 'shoulder[ -]+to[ -]+overhead|s2oh|stoh'],
            ['Thruster', 'thrusters?'],
            ['Deadlift', 'dead[ -]*lifts?|levantamentos?[ -]+terra'],
            ['Romanian Deadlift', 'romanian[ -]+dead[ -]*lifts?|rdl', 'Romanian'],
            ['Front Squat', 'front[ -]+squats?|agachamentos?[ -]+fronta(?:l|is)', 'Front'],
            ['Back Squat', 'back[ -]+squats?', 'Back'],
            ['Overhead Squat', 'overhead[ -]+squats?|ohs', 'Overhead'],
            ['Bench Press', 'bench[ -]+press(?:es)?|supino'],
        ],
        'Dumbbell' => [
            ['Dumbbell Snatch', '(?:db|dumbbells?)[ -]+snatch(?:es)?|snatch(?:es)?[ -]+(?:db|dumbbells?)'],
            ['Alternating Dumbbell Snatch', '(?:alt|alternating)\.?[ -]+(?:db|dumbbells?)[ -]+snatch(?:es)?|(?:db|dumbbells?)[ -]+snatch(?:es)?[ -]+(?:alt|alternating|alternados?)', 'Alternado'],
            ['Dumbbell Clean', '(?:db|dumbbells?)[ -]+cleans?'],
            ['Dumbbell Clean & Jerk', '(?:db|dumbbells?)[ -]+(?:cleans?\s*(?:&|\+|and|e)\s*jerks?|c\s*&\s*j)'],
            ['Dumbbell Thruster', '(?:db|dumbbells?)[ -]+thrusters?'],
            ['Dumbbell Deadlift', '(?:db|dumbbells?)[ -]+dead[ -]*lifts?'],
            ['Dumbbell Push Press', '(?:db|dumbbells?)[ -]+push[ -]+press(?:es)?'],
            ['Dumbbell Shoulder to Overhead', '(?:db|dumbbells?)[ -]+(?:shoulder[ -]+to[ -]+overhead|s2oh|stoh)'],
            ['Dumbbell Lunges', '(?:db|dumbbells?)[ -]+(?:(?:walking|reverse)[ -]+)?lunges?'],
        ],
        'Kettlebell' => [
            ['Kettlebell Swing', '(?:kb|kettlebells?)[ -]+swings?|swings?[ -]+(?:kb|kettlebells?)'],
            ['American KB Swing', 'american[ -]+(?:kb|kettlebells?)[ -]+swings?|american[ -]+swings?', 'American'],
            ['Russian KB Swing', 'russian[ -]+(?:kb|kettlebells?)[ -]+swings?|russian[ -]+swings?', 'Russian'],
            ['Kettlebell Clean', '(?:kb|kettlebells?)[ -]+cleans?'],
            ['Kettlebell Snatch', '(?:kb|kettlebells?)[ -]+snatch(?:es)?'],
            ['Goblet Squat', '(?:(?:kb|kettlebells?)[ -]+)?goblet[ -]+squats?'],
            ['Kettlebell Deadlift', '(?:kb|kettlebells?)[ -]+dead[ -]*lifts?'],
            ['Kettlebell Farmer Carry', '(?:kb|kettlebells?)[ -]+farmers?(?:\x27)?[ -]+carr(?:y|ies)'],
        ],
        'Wall Ball' => [['Wall Ball', 'wall[ -]*balls?(?:[ -]+shots?)?|wb']],
        'Medicine Ball' => [['Medicine Ball Clean', '(?:medicine[ -]+balls?|med[ -]+balls?)[ -]+cleans?']],
        'Sandbag' => [['Sandbag Carry', 'sand[ -]*bag[ -]+carr(?:y|ies)'], ['Sandbag Clean', 'sand[ -]*bag[ -]+cleans?']],
        'Sled' => [['Sled Push', 'sled[ -]+push(?:es)?'], ['Sled Pull', 'sled[ -]+pulls?']],
        'Plate' => [['Plate Ground to Overhead', 'plates?[ -]+(?:ground[ -]+to[ -]+overhead|g2oh)']],
    ];

    public function entries(bool $includeInactive = false): array
    {
        $private = LoadMovement::query()->when(! $includeInactive, fn ($query) => $query->where('active', true))
            ->orderBy('equipment')->orderBy('name')->get()->map($this->serialize(...))->all();

        return [...$this->base(), ...$private];
    }

    public function serialize(LoadMovement $movement): array
    {
        return [
            'id' => $movement->id, 'key' => 'box:'.$movement->id, 'origin' => 'box',
            ...$movement->only(['name', 'normalized_name', 'equipment', 'variation', 'modalities', 'aliases', 'active']),
        ];
    }

    public function base(): array
    {
        $entries = [];
        foreach (self::BASE as $equipment => $definitions) {
            foreach ($definitions as $definition) {
                [$name] = $definition;
                $modalities = ['crossfit', 'functional'];
                if (in_array($equipment, ['Wall Ball', 'Sandbag', 'Sled'], true) || in_array($name, ['Dumbbell Lunges', 'Kettlebell Farmer Carry'], true)) {
                    $modalities[] = 'hyrox';
                }
                $entries[] = [
                    'id' => null, 'key' => 'base:'.Str::slug($name), 'origin' => 'base',
                    'name' => $name, 'normalized_name' => LoadLibrary::key($name), 'equipment' => $equipment,
                    'variation' => $definition[2] ?? null, 'modalities' => $modalities, 'active' => true,
                    'aliases' => match ($name) {
                        'Clean & Jerk' => ['C&J', 'Clean and Jerk'],
                        'Dumbbell Snatch' => ['DB Snatch'],
                        'Kettlebell Swing' => ['KB Swing'],
                        'Romanian Deadlift' => ['RDL'],
                        'Shoulder to Overhead' => ['S2OH', 'STOH'],
                        'Wall Ball' => ['WB', 'Wall Ball Shot'],
                        default => [Str::lower($name)],
                    },
                ];
            }
        }

        return $entries;
    }

    public function pattern(array $movement): string
    {
        if ($movement['origin'] === 'box') {
            return implode('|', array_map(
                fn (string $phrase): string => str_replace(['\\ ', ' '], '[ -]+', preg_quote(LoadLibrary::key($phrase), '/')),
                array_unique([$movement['name'], ...$movement['aliases']]),
            ));
        }

        foreach (self::BASE[$movement['equipment']] as [$name, $pattern]) {
            if ($name === $movement['name']) {
                return $pattern;
            }
        }

        throw new \LogicException('Movimento sem padrão de reconhecimento.');
    }
}
