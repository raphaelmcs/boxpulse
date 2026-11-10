<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workouts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('box_id')->constrained()->restrictOnDelete();
            $table->foreignId('training_modality_id')->constrained()->restrictOnDelete();
            $table->string('title', 160);
            $table->date('scheduled_for');
            $table->string('status', 20)->default('draft');
            $table->timestamps();
            $table->index(['box_id', 'scheduled_for']);
            $table->index(['box_id', 'training_modality_id', 'scheduled_for']);
            $table->unique(['box_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workouts');
    }
};
