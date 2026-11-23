<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workouts', function (Blueprint $table): void {
            $table->json('editor_draft')->nullable();
            $table->json('presentation_options')->nullable();
            $table->unsignedBigInteger('edit_version')->default(1);
            $table->uuid('editor_create_id')->nullable();
            $table->uuid('editor_write_id')->nullable();
            $table->char('editor_write_hash', 64)->nullable();
            $table->unique(['box_id', 'editor_create_id']);
        });

        Schema::table('workout_stations', fn (Blueprint $table) => $table->json('text_style')->nullable());

        foreach (['workout_blocks', 'timer_sessions'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->json('text_style')->nullable();
                $table->json('load_entries')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('workout_stations', fn (Blueprint $table) => $table->dropColumn('text_style'));
        foreach (['workout_blocks', 'timer_sessions'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['text_style', 'load_entries']));
        }
        Schema::table('workouts', function (Blueprint $table): void {
            $table->dropUnique(['box_id', 'editor_create_id']);
            $table->dropColumn(['editor_draft', 'presentation_options', 'edit_version', 'editor_create_id', 'editor_write_id', 'editor_write_hash']);
        });
    }
};
