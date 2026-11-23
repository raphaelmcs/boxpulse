<?php

namespace Database\Seeders;

use App\Models\LoadMovement;
use App\Models\User;
use App\Tenancy\CurrentBox;
use Illuminate\Database\Seeder;

class LoadMovementSeeder extends Seeder
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
            LoadMovement::firstOrCreate(
                ['equipment' => 'Dumbbell', 'normalized_name' => 'dumbbell step-up'],
                ['name' => 'Dumbbell Step-up', 'modalities' => ['crossfit', 'functional'], 'aliases' => ['DB step up']],
            );
        } finally {
            $context->clear();
        }
    }
}
