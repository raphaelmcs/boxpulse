<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boxes', function (Blueprint $table): void {
            $table->char('tv_access_hash', 64)->nullable()->unique();
            $table->text('tv_access_token')->nullable();
        });
        Schema::table('workout_sessions', function (Blueprint $table): void {
            $table->index(['box_id', 'started_at', 'id'], 'workout_sessions_box_tv_started_index');
        });
    }

    public function down(): void
    {
        Schema::table('workout_sessions', function (Blueprint $table): void {
            $table->dropIndex('workout_sessions_box_tv_started_index');
        });
        Schema::table('boxes', function (Blueprint $table): void {
            $table->dropUnique(['tv_access_hash']);
            $table->dropColumn(['tv_access_hash', 'tv_access_token']);
        });
    }
};
