<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('box_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('workout_id');
            $table->unsignedBigInteger('responsible_user_id');
            $table->unsignedBigInteger('active_workout_id')->nullable();
            $table->string('title', 160);
            $table->string('modality_name', 80);
            $table->date('scheduled_for');
            $table->string('status', 20)->default('active');
            $table->unsignedTinyInteger('current_position')->default(0);
            $table->boolean('auto_advance')->default(false);
            $table->unsignedTinyInteger('preparation_override')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->uuid('controller_client_id')->nullable();
            $table->unsignedBigInteger('controller_user_id')->nullable();
            $table->timestamp('controller_lease_expires_at', 3)->nullable();
            $table->timestamp('started_at', 3)->nullable();
            $table->timestamp('finished_at', 3)->nullable();
            $table->timestamps(3);
            $table->unique(['box_id', 'id']);
            $table->unique(['box_id', 'active_workout_id']);
            $table->index(['box_id', 'status', 'created_at']);
            $table->foreign(['box_id', 'workout_id'])->references(['box_id', 'id'])->on('workouts')->restrictOnDelete();
            $table->foreign(['box_id', 'responsible_user_id'])->references(['box_id', 'id'])->on('users')->restrictOnDelete();
            $table->foreign(['box_id', 'controller_user_id'])->references(['box_id', 'id'])->on('users')->restrictOnDelete();
        });
        Schema::create('timer_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('box_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('workout_session_id');
            $table->unsignedTinyInteger('position');
            $table->string('block_type', 20);
            $table->string('title', 160)->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->string('timer_type', 24);
            $table->json('config');
            $table->json('stations_snapshot');
            $table->string('state', 20)->default('idle');
            $table->unsignedBigInteger('elapsed_ms')->default(0);
            $table->timestamp('started_at', 3)->nullable();
            $table->timestamp('effective_started_at', 3)->nullable();
            $table->timestamp('paused_at', 3)->nullable();
            $table->timestamp('finished_at', 3)->nullable();
            $table->timestamps(3);
            $table->unique(['box_id', 'workout_session_id', 'position'], 'timer_session_position_unique');
            $table->foreign(['box_id', 'workout_session_id'])->references(['box_id', 'id'])->on('workout_sessions')->restrictOnDelete();
        });
        Schema::create('session_commands', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('box_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('workout_session_id');
            $table->uuid('command_id');
            $table->string('action', 24);
            $table->char('request_hash', 64);
            $table->unsignedInteger('result_version');
            $table->timestamps(3);
            $table->unique(['box_id', 'workout_session_id', 'command_id'], 'session_command_idempotency_unique');
            $table->foreign(['box_id', 'workout_session_id'])->references(['box_id', 'id'])->on('workout_sessions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_commands');
        Schema::dropIfExists('timer_sessions');
        Schema::dropIfExists('workout_sessions');
    }
};
