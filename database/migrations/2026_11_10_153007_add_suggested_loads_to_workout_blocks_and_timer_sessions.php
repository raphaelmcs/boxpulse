<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['workout_blocks', 'timer_sessions'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->json('suggested_loads')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['timer_sessions', 'workout_blocks'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('suggested_loads'));
        }
    }
};
