<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hormone_snapshots', function (Blueprint $table) {

            $table->id();

            $table->foreignId('cycle_id')
                ->constrained('menstrual_cycles')
                ->cascadeOnDelete();

            $table->date('snapshot_date');

            $table->enum('estrogen', [
                'very_low',
                'low',
                'moderate',
                'high',
                'peak'
            ])->nullable();

            $table->enum('progesterone', [
                'very_low',
                'low',
                'moderate',
                'high',
                'peak'
            ])->nullable();

            $table->enum('lh', [
                'very_low',
                'low',
                'moderate',
                'high',
                'peak'
            ])->nullable();

            $table->enum('fsh', [
                'very_low',
                'low',
                'moderate',
                'high'
            ])->nullable();

            $table->boolean('modeled')->default(true);

            $table->enum('source', [
                'phase_model',
                'lab',
                'ai',
                'wearable'
            ])->default('phase_model');

            $table->text('note')->nullable();

            $table->timestamps();

            $table->unique(['cycle_id','snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hormone_snapshots');
    }
};
