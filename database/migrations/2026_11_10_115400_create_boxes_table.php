<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boxes', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('head_coach_name', 120);
            $table->string('timezone', 64)->default('America/Sao_Paulo');
            $table->string('logo_path')->nullable();
            $table->char('primary_color', 7)->default('#C8FA3C');
            $table->char('secondary_color', 7)->default('#38BDF8');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boxes');
    }
};
