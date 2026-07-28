<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('symptom_logs', function (Blueprint $table) {

            $table->id();

            $table->foreignId('cycle_id')
                ->constrained('menstrual_cycles')
                ->cascadeOnDelete();

            $table->date('log_date');

            $table->unsignedTinyInteger('pain_level')->nullable();

            $table->enum('mood',[
                'very_low',
                'low',
                'neutral',
                'good',
                'excellent'
            ])->nullable();

            $table->enum('energy',[
                'very_low',
                'low',
                'moderate',
                'high',
                'very_high'
            ])->nullable();

            $table->boolean('cramps')->default(false);
            $table->boolean('bloating')->default(false);
            $table->boolean('headache')->default(false);
            $table->boolean('fatigue')->default(false);
            $table->boolean('acne')->default(false);
            $table->boolean('breast_tenderness')->default(false);

            $table->boolean('nausea')->default(false);
            $table->boolean('insomnia')->default(false);

            $table->unsignedTinyInteger('libido')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['cycle_id','log_date']);

            $table->index('mood');
            $table->index('energy');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('symptom_logs');
    }
};

