<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_stations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('box_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('workout_block_id');
            $table->unsignedSmallInteger('position');
            $table->string('name', 160)->nullable();
            $table->text('description')->nullable();
            $table->decimal('distance', 10, 2)->nullable();
            $table->unsignedInteger('repetitions')->nullable();
            $table->decimal('load', 8, 2)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('rest_seconds')->nullable();
            $table->unsignedInteger('transition_seconds')->nullable();
            $table->timestamps();
            $table->unique(['box_id', 'workout_block_id', 'position'], 'stations_block_position_unique');
            $table->foreign(['box_id', 'workout_block_id'])->references(['box_id', 'id'])->on('workout_blocks')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_stations');
    }
};
