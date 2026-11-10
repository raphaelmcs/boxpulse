<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workouts', fn (Blueprint $table) => $table->unsignedInteger('content_version')->default(1));
        Schema::table('workout_sessions', function (Blueprint $table): void {
            $table->unsignedInteger('workout_version')->nullable();
            $table->unsignedBigInteger('superseded_by_id')->nullable();
            $table->foreign(['box_id', 'superseded_by_id'])->references(['box_id', 'id'])->on('workout_sessions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workout_sessions', function (Blueprint $table): void {
            $table->dropForeign(['box_id', 'superseded_by_id']);
            $table->dropColumn(['workout_version', 'superseded_by_id']);
        });
        Schema::table('workouts', fn (Blueprint $table) => $table->dropColumn('content_version'));
    }
};
