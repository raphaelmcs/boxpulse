<?php

namespace Database\Seeders;

use App\Models\LoadPreset;
use App\Models\User;
use App\Tenancy\CurrentBox;
use Illuminate\Database\Seeder;

class LoadPresetSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $demo = User::where('username', 'boxpulse.demo')->first();
        if (! $demo) {
            return;
        }

        $context = app(CurrentBox::class);
        $context->set($demo->box_id);
        try {
            LoadPreset::firstOrCreate(
                ['equipment' => 'Dumbbell', 'movement' => 'Dumbbell Snatch', 'reference' => 'Biblioteca da box de demonstração'],
                ['levels' => ['rx' => ['male' => 22.5, 'female' => 15], 'scale' => ['male' => 15, 'female' => 10]], 'unit' => 'kg']
            );
        } finally {
            $context->clear();
        }
    }
}
