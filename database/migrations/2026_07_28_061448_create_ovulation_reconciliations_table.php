<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ovulation_reconciliations', function (Blueprint $table) {

            $table->id();

            $table->foreignId('cycle_id')
                ->constrained('menstrual_cycles')
                ->cascadeOnDelete();

            // Individual Predictions
            $table->unsignedTinyInteger('calendar_predicted_day')->nullable();
            $table->unsignedTinyInteger('bbt_confirmed_day')->nullable();
            $table->unsignedTinyInteger('lh_surge_day')->nullable();
            $table->unsignedTinyInteger('mucus_peak_day')->nullable();

            // Final Decision
            $table->unsignedTinyInteger('final_confirmed_day')->nullable();

            $table->enum('final_source',[
                'calendar',
                'bbt',
                'opk',
                'mucus',
                'combined'
            ])->nullable();

            // Difference
            $table->smallInteger('offset_days')->default(0);

            $table->unsignedTinyInteger('luteal_phase_length')->default(14);

            $table->boolean('is_reconciled')->default(false);

            $table->timestamp('reconciled_at')->nullable();

            $table->timestamps();

            $table->unique('cycle_id');

            $table->index('final_source');
            $table->index('is_reconciled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ovulation_reconciliations');
    }
};
