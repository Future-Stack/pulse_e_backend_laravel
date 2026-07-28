<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menstrual_cycles', function (Blueprint $table) {

            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Cycle Dates
            $table->date('period_start_date');
            $table->date('period_end_date')->nullable();

            // Cycle Information
            $table->unsignedTinyInteger('cycle_length')->nullable();
            $table->unsignedTinyInteger('period_length')->nullable();

            // Ovulation
            $table->unsignedTinyInteger('predicted_ovulation_day')->nullable();
            $table->unsignedTinyInteger('confirmed_ovulation_day')->nullable();

            // Fertile Window
            $table->unsignedTinyInteger('fertile_start_day')->nullable();
            $table->unsignedTinyInteger('fertile_end_day')->nullable();

            // Phase
            $table->enum('current_phase',[
                'menstrual',
                'follicular',
                'ovulatory',
                'luteal'
            ])->nullable();

            // Prediction Source
            $table->enum('prediction_source',[
                'calendar',
                'bbt',
                'opk',
                'mucus',
                'combined'
            ])->default('calendar');

            // Status
            $table->boolean('is_confirmed')->default(false);
            $table->boolean('is_completed')->default(false);

            $table->timestamps();

            $table->index(['user_id','period_start_date']);
            $table->index('current_phase');
            $table->index('is_completed');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menstrual_cycles');
    }
};
