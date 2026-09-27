<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cycle_fertility_overviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('cycle_id')->nullable()->constrained('menstrual_cycles')->nullOnDelete();
            $table->date('overview_date');
            $table->integer('current_cycle_day')->nullable();
            $table->string('current_phase', 50)->nullable();
            $table->date('period_start_date')->nullable();
            $table->date('period_end_date')->nullable();
            $table->json('fertile_window')->nullable();
            $table->json('fertile_window_prediction')->nullable();
            $table->json('hormone_trends')->nullable();
            $table->json('today_insights')->nullable();
            $table->json('ai_insights')->nullable();
            $table->json('bbt_analysis')->nullable();
            $table->json('cycle_history')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'overview_date'], 'user_overview_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_fertility_overviews');
    }
};
