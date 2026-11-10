<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('box_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('workout_id');
            $table->string('type', 20);
            $table->unsignedTinyInteger('position');
            $table->string('title', 160)->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->string('timer_type', 24)->default('none');
            $table->json('timer_settings')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamps();
            $table->unique(['box_id', 'id']);
            $table->unique(['box_id', 'workout_id', 'position'], 'blocks_workout_position_unique');
            $table->foreign(['box_id', 'workout_id'])->references(['box_id', 'id'])->on('workouts')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_blocks');
    }
};
