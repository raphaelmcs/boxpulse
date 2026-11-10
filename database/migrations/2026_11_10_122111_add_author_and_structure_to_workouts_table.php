<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unique(['box_id', 'id']);
        });
        Schema::table('workouts', function (Blueprint $table): void {
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('structure', 10)->default('main');
            $table->foreign(['box_id', 'created_by'])->references(['box_id', 'id'])->on('users')->restrictOnDelete();
            $table->index(['box_id', 'status', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::table('workouts', function (Blueprint $table): void {
            $table->dropForeign(['box_id', 'created_by']);
            $table->dropIndex(['box_id', 'status', 'scheduled_for']);
            $table->dropColumn(['created_by', 'structure']);
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropUnique(['box_id', 'id']));
    }
};
