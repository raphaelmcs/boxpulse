<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('load_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('box_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->string('normalized_name', 120);
            $table->string('equipment', 80);
            $table->string('variation', 80)->nullable();
            $table->json('modalities');
            $table->json('aliases');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['box_id', 'id']);
            $table->unique(['box_id', 'equipment', 'normalized_name'], 'load_movements_box_name_unique');
        });

        Schema::table('load_presets', function (Blueprint $table): void {
            $table->unsignedBigInteger('movement_id')->nullable();
            $table->string('name', 120)->nullable();
            $table->string('context', 20)->nullable();
            $table->text('notes')->nullable();
            $table->foreign(['box_id', 'movement_id'], 'load_presets_box_movement_foreign')
                ->references(['box_id', 'id'])->on('load_movements')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('load_presets', function (Blueprint $table): void {
            $table->dropForeign('load_presets_box_movement_foreign');
            $table->dropColumn(['movement_id', 'name', 'context', 'notes']);
        });
        Schema::dropIfExists('load_movements');
    }
};
