<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('load_presets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('box_id')->constrained()->cascadeOnDelete();
            $table->string('equipment', 80);
            $table->string('movement', 120)->nullable();
            $table->json('levels');
            $table->string('unit', 2)->default('kg');
            $table->string('reference', 160)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['box_id', 'active', 'equipment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('load_presets');
    }
};
