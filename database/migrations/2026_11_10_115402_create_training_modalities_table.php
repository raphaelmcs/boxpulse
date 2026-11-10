<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_modalities', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('name', 80);
        });

        DB::table('training_modalities')->insert([
            ['slug' => 'crossfit', 'name' => 'CrossFit'],
            ['slug' => 'hyrox', 'name' => 'HYROX'],
            ['slug' => 'functional', 'name' => 'Treinamento Funcional'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('training_modalities');
    }
};
