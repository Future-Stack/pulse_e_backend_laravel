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
        Schema::create('perimenopause_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('stage_title')->default('Perimenopause — Year 2');
            $table->string('stage_subtitle')->default('Irregular cycles for 18 months · FSH elevated');
            $table->boolean('is_tracker_active')->default(true);
            $table->unsignedSmallInteger('irregular_cycles_months')->default(18);
            $table->decimal('fsh_level', 5, 2)->default(18.4);
            $table->string('fsh_status')->default('elevated');
            $table->decimal('avg_hot_flashes_per_day', 4, 1)->default(4.3);
            $table->decimal('sleep_disruption_nights_per_week', 3, 1)->default(3.2);
            $table->string('mood_instability')->default('Mild-Moderate');
            $table->timestamps();
        });

        Schema::create('vasomotor_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('log_date');
            $table->string('day_of_week', 10); // Mon, Tue, Wed, Thu, Fri, Sat, Sun
            $table->unsignedSmallInteger('mild_count')->default(0);      // 1-3
            $table->unsignedSmallInteger('moderate_count')->default(0);  // 4-5
            $table->unsignedSmallInteger('intense_count')->default(0);   // 6+
            $table->unsignedSmallInteger('total_episodes')->default(0);
            $table->decimal('avg_intensity', 3, 1)->default(0.0);
            $table->string('peak_time')->nullable(); // e.g. "Thursday evening"
            $table->timestamps();

            $table->unique(['user_id', 'log_date']);
        });

        Schema::create('gsm_checkin_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('checkin_date');
            $table->enum('vaginal_dryness', ['none', 'mild', 'moderate', 'severe', 'very_severe'])->default('moderate');
            $table->enum('urinary_frequency', ['none', 'mild', 'moderate', 'severe', 'very_severe'])->default('mild');
            $table->enum('pelvic_discomfort', ['none', 'mild', 'moderate', 'severe', 'very_severe'])->default('mild');
            $table->enum('libido_impact', ['none', 'mild', 'moderate', 'severe', 'very_severe'])->default('moderate');
            $table->timestamps();

            $table->index(['user_id', 'checkin_date']);
        });

        Schema::create('menopause_symptom_insights', function (Blueprint $table) {
            $table->id();
            $table->string('title'); // e.g. "Hot Flashes -> Sleep"
            $table->unsignedTinyInteger('link_percentage'); // e.g. 87
            $table->text('description'); // "Hot flash episodes after 10pm directly correlate with 47% reduction in deep sleep duration."
            $table->string('category')->default('symptom_matrix');
            $table->unsignedSmallInteger('display_order')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menopause_symptom_insights');
        Schema::dropIfExists('gsm_checkin_logs');
        Schema::dropIfExists('vasomotor_logs');
        Schema::dropIfExists('perimenopause_profiles');
    }
};
