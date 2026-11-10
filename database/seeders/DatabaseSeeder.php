<?php

namespace Database\Seeders;

use App\Actions\RegisterBox;
use App\Models\TrainingModality;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        if (! User::where('username', 'boxpulse.demo')->exists()) {
            app(RegisterBox::class)->handle([
                'modalities' => TrainingModality::pluck('id')->all(),
                'box_name' => 'Box Horizonte • Demo',
                'head_coach_name' => 'Ana Coach',
                'name' => 'Coach Demo',
                'username' => 'boxpulse.demo',
                'password' => Str::password(32),
            ]);
        }
        $this->call(WorkoutDemoSeeder::class);
    }
}
