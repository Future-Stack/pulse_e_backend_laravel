<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phase_insights', function (Blueprint $table) {

            $table->id();

            $table->foreignId('cycle_id')
                ->constrained('menstrual_cycles')
                ->cascadeOnDelete();

            $table->date('insight_date');

            $table->enum('phase', [
                'menstrual',
                'follicular',
                'ovulatory',
                'luteal'
            ]);

            $table->text('education')->nullable();
            $table->text('energy_note')->nullable();
            $table->text('hormone_note')->nullable();
            $table->text('focus_note')->nullable();
            $table->text('skin_note')->nullable();
            $table->text('nutrition_note')->nullable();
            $table->text('exercise_note')->nullable();

            $table->boolean('ai_generated')->default(true);

            $table->timestamps();

            $table->unique(['cycle_id','insight_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phase_insights');
    }
};
