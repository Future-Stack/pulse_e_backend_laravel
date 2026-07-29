<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cycle_settings', function (Blueprint $table) {

            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Cycle Defaults
            $table->unsignedTinyInteger('average_cycle_length')->default(28);
            $table->unsignedTinyInteger('average_period_length')->default(5);
            $table->unsignedTinyInteger('luteal_phase_length')->default(14);

            // Prediction
            $table->enum('prediction_method', [
                'calendar',
                'bbt',
                'opk',
                'combined'
            ])->default('combined');

            // Temperature
            $table->enum('temperature_unit', [
                'C',
                'F'
            ])->default('F');

            // Notifications
            $table->boolean('period_reminder')->default(true);
            $table->boolean('fertility_reminder')->default(true);
            $table->boolean('bbt_reminder')->default(true);
            $table->boolean('opk_reminder')->default(true);

            // Privacy
            $table->boolean('allow_ai_prediction')->default(true);

            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_settings');
    }
};

