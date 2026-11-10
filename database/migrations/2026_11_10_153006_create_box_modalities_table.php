<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('box_modalities')) {
            return;
        }
        Schema::create('box_modalities', function (Blueprint $table): void {
            $table->foreignId('box_id')->constrained('boxes')->cascadeOnDelete();
            $table->foreignId('training_modality_id')->constrained('training_modalities')->restrictOnDelete();
            $table->unique(['box_id', 'training_modality_id']);
        });
        $this->backfillExistingBoxes();
    }

    public function backfillExistingBoxes(): void
    {
        // Global legacy backfill is confined to this migration; application writes use the authenticated box relation.
        $modalities = DB::table('training_modalities')->pluck('id');
        DB::table('boxes')->orderBy('id')->chunkById(100, function ($boxes) use ($modalities): void {
            foreach ($boxes as $box) {
                foreach ($modalities as $modality) {
                    DB::table('box_modalities')->insertOrIgnore(['box_id' => $box->id, 'training_modality_id' => $modality]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('box_modalities');
    }
};
