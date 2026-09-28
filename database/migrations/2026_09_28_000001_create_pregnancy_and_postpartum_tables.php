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
        Schema::create('user_pregnancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('due_date');
            $table->date('last_menstrual_period_date')->nullable();
            $table->date('conception_date')->nullable();
            $table->enum('status', ['active', 'completed', 'miscarriage'])->default('active');
            $table->date('ended_at')->nullable();
            $table->date('delivery_date')->nullable();
            $table->enum('delivery_type', ['vaginal', 'c_section', 'other'])->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('pregnancy_weekly_guides', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('week_number')->unique();
            $table->string('trimester', 50);
            $table->string('baby_size_comparison');
            $table->string('approx_size_text')->nullable();
            $table->text('baby_development');
            $table->text('your_body');
            $table->text('nutrition_focus');
            $table->text('safe_exercise');
            $table->text('clinical_warning_signs')->nullable();
            $table->timestamps();
        });

        Schema::create('pregnancy_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pregnancy_id')->constrained('user_pregnancies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->unsignedTinyInteger('target_week');
            $table->string('date_label')->nullable();
            $table->date('scheduled_date')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('postpartum_recoveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('pregnancy_id')->nullable()->constrained('user_pregnancies')->nullOnDelete();
            $table->date('delivery_date');
            $table->unsignedTinyInteger('current_week')->default(1);
            $table->unsignedSmallInteger('physical_recovery_percent')->default(72);
            $table->unsignedSmallInteger('hormonal_balance_percent')->default(58);
            $table->unsignedSmallInteger('sleep_quality_percent')->default(45);
            $table->integer('sleep_change_diff')->default(8);
            $table->unsignedSmallInteger('energy_levels_percent')->default(61);
            $table->string('screening_name')->default('Edinburgh Postnatal Depression Scale');
            $table->boolean('screening_due')->default(true);
            $table->string('screening_due_text')->default('due today');
            $table->string('mood_stability', 50)->default('Stable');
            $table->string('anxiety_level', 50)->default('Mild');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('postpartum_recoveries');
        Schema::dropIfExists('pregnancy_milestones');
        Schema::dropIfExists('pregnancy_weekly_guides');
        Schema::dropIfExists('user_pregnancies');
    }
};
