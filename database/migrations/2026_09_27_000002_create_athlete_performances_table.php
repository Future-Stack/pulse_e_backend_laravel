<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('athlete_performances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('performance_date');
            $table->integer('readiness_score')->nullable();
            $table->string('readiness_level', 50)->nullable();
            $table->json('hrv')->nullable();
            $table->json('recovery')->nullable();
            $table->json('training_load')->nullable();
            $table->json('metrics')->nullable();
            $table->json('fatigue_alerts')->nullable();
            $table->json('cycle_info')->nullable();
            $table->json('phase_cards')->nullable();
            $table->timestamp('next_update')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'performance_date'], 'user_performance_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('athlete_performances');
    }
};
