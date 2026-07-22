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
        Schema::create('health_trends', function (Blueprint $table) {

            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('title')->nullable();
            $table->enum('period', [
                                '7d',
                                '30d'
                            ])->default('30d');

            // 7d / 30d options
            $table->json('range_options')->nullable();

            // Sleep vs Energy Chart
            $table->json('sleep_energy_correlation_chart')->nullable();

            // Diagram
            $table->json('sleep_energy_correlation_diagram')->nullable();

            // Hormones x Mood
            $table->json('hormone_mood')->nullable();

            $table->enum('status', [
                'pending',
                'processing',
                'completed',
                'failed',
            ])->default('pending');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('health_trends');
    }
};