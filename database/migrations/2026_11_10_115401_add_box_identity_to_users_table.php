<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('box_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('username', 40)->nullable()->unique();
            $table->string('role', 20)->default('coach');
            $table->string('email')->nullable()->change();
            $table->index(['box_id', 'role']);
        });

        DB::table('users')->orderBy('id')->eachById(function (object $user): void {
            DB::table('users')->where('id', $user->id)->update(['username' => 'legacy-'.$user->id]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 40)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['box_id']);
            $table->dropIndex(['box_id', 'role']);
            $table->dropUnique(['username']);
            $table->dropColumn(['box_id', 'username', 'role']);
        });
    }
};
