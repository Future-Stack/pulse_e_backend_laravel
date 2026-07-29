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
        Schema::create('period_logs', function (Blueprint $table) {

            $table->id();

            // User
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Menstrual Cycle
            $table->foreignId('cycle_id')
                ->constrained('menstrual_cycles')
                ->cascadeOnDelete();

            // Log Date
            $table->date('log_date');

            // Flow
            $table->enum('flow', [
                'spotting',
                'light',
                'medium',
                'heavy',
            ]);

            // Symptoms
            $table->boolean('clotting')->default(false);
            $table->boolean('cramps')->default(false);
            $table->boolean('headache')->default(false);
            $table->boolean('fatigue')->default(false);

            // Pain Level (0-10)
            $table->unsignedTinyInteger('pain_level')
                ->nullable()
                ->comment('0-10');

            // Optional Notes
            $table->text('notes')->nullable();

            $table->timestamps();

            // One period log per user per cycle per day
            $table->unique(['user_id', 'cycle_id', 'log_date']);

            // Indexes
            $table->index('flow');
            $table->index('log_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('period_logs');
    }
};