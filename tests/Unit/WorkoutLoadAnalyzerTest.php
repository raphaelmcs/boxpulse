<?php

namespace Tests\Unit;

use App\Workouts\LoadLibrary;
use App\Workouts\WorkoutLoadAnalyzer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WorkoutLoadAnalyzerTest extends TestCase
{
    public static function movements(): array
    {
        return [
            ['40 DB SNATCH 22,5/15 KG', 'Dumbbell Snatch', 'Dumbbell', ['male' => '22,5', 'female' => '15', 'unit' => 'kg']],
            ['30 WALL BALL 9/6 KG', 'Wall Ball', 'Wall Ball', ['male' => '9', 'female' => '6', 'unit' => 'kg']],
            ['21-15-9 THRUSTER 42,5/30 KG', 'Thruster', 'Barbell', ['male' => '42,5', 'female' => '30', 'unit' => 'kg']],
            ['5 BB FRONT SQUATS 70% 1RM', 'Front Squat', 'Barbell', null],
            ['5 FRONT SQUATS 70% do 1RM (120 kg)', 'Front Squat', 'Barbell', null],
            ['5 FRONT SQUATS 84 kg (70% do 1RM)', 'Front Squat', 'Barbell', ['male' => '84', 'female' => null, 'unit' => 'kg']],
            ['Back Squat 120 lb', 'Back Squat', 'Barbell', ['male' => '120', 'female' => null, 'unit' => 'lb']],
            ['Deadlift 10 m', 'Deadlift', 'Barbell', null],
            ['Clean 20-30 kg', 'Clean', 'Barbell', null],
            ['10 Clean & Jerks', 'Clean & Jerk', 'Barbell', null],
            ['10 Snatches', 'Snatch', 'Barbell', null],
            ['Push Press 1 min', 'Push Press', 'Barbell', null],
            ['10 Shoulder to Overhead 3 rounds', 'Shoulder to Overhead', 'Barbell', null],
            ['15 KB Swings', 'Kettlebell Swing', 'Kettlebell', null],
            ['Sandbag Carry 200 m', 'Sandbag Carry', 'Sandbag', null],
            ['Sled Push 0/0 kg', 'Sled Push', 'Sled', ['male' => '0', 'female' => '0', 'unit' => 'kg']],
            ['Sled Pull 8 feet', 'Sled Pull', 'Sled', null],
            ['20 POWER SNATCH', 'Power Snatch', 'Barbell', null],
            ['15 CLEAN & JERK', 'Clean & Jerk', 'Barbell', null],
            ['12 PUSH JERKS', 'Push Jerk', 'Barbell', null],
            ['10 DEADLIFTS 100/70 KG', 'Deadlift', 'Barbell', ['male' => '100', 'female' => '70', 'unit' => 'kg']],
            ['12 DB CLEAN & JERKS', 'Dumbbell Clean & Jerk', 'Dumbbell', null],
            ['10 ALTERNATING DB SNATCHES', 'Alternating Dumbbell Snatch', 'Dumbbell', null],
            ['15 RUSSIAN KB SWINGS', 'Russian KB Swing', 'Kettlebell', null],
            ['10 RDL 50 lb', 'Romanian Deadlift', 'Barbell', ['male' => '50', 'female' => null, 'unit' => 'lb']],
        ];
    }

    #[DataProvider('movements')]
    public function test_recognizes_movements_and_requires_unambiguous_absolute_units(string $text, string $movement, string $equipment, ?array $weight): void
    {
        $analyzer = new WorkoutLoadAnalyzer(new LoadLibrary);
        $items = $analyzer->analyze(['type' => 'workout', 'description' => $text], []);

        $this->assertCount(1, $items);
        $this->assertSame($movement, $items[0]['movement']);
        $this->assertSame($equipment, $items[0]['equipment']);
        $this->assertSame($weight, $items[0]['explicit']);
    }

    public function test_keeps_movements_separate_and_unmatched_units_out_of_absolute_prescriptions(): void
    {
        $analyzer = new WorkoutLoadAnalyzer(new LoadLibrary);
        $items = $analyzer->analyze([
            'type' => 'skill',
            'description' => "500 m Run\nAMRAP 20'\n5 Front Squats 70% + 20 DB Snatches 15kg\n2 WB 9kg or 6kg",
            'stations' => [['name' => 'Sled Pull', 'load' => 80, 'description' => '20 m']],
        ], []);

        $this->assertCount(4, $items);
        $this->assertSame('70%', $items[0]['relative']);
        $this->assertNull($items[0]['explicit']);
        $this->assertSame('15', $items[1]['explicit']['male']);
        $this->assertTrue($items[2]['review']);
        $this->assertNull($items[2]['explicit']);
        $this->assertSame('station', $items[3]['field']);
        $this->assertSame('80', $items[3]['explicit']['male']);
    }

    public function test_labels_context_and_distinguishes_per_implement_and_total_weights(): void
    {
        $analyzer = new WorkoutLoadAnalyzer(new LoadLibrary);
        $items = $analyzer->analyze(['type' => 'workout', 'title' => 'Condicionamento',
            'description' => "10 DB THRUSTERS 2×22,5 kg\n6 DB SNATCHES unilateral 15 lb\n20 KB Swing 30 kg total"], []);

        $this->assertSame(['double', 'single', 'total'], array_column($items, 'implement'));
        $this->assertSame(['conditioning', 'conditioning', 'conditioning'], array_column($items, 'context'));
        $this->assertSame(['22,5', '15', '30'], array_column(array_column($items, 'explicit'), 'male'));
    }
}
