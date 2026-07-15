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
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique(); // free, premium, elite
            $table->string('name');
            $table->text('description')->nullable();

            $table->decimal('price_monthly', 8, 2)->default(0);
            $table->decimal('price_annual', 8, 2)->nullable();

            // Sentinel convention: -1 = unlimited, 0 = not included.
            $table->integer('skin_scans_limit')->default(0);
            $table->integer('ai_coaching_limit')->default(0);
            $table->string('ai_coaching_model')->nullable(); // e.g. "haiku", "all"
            $table->integer('deep_reports_limit')->default(0);
            $table->enum('daily_summary_frequency', ['weekly', 'unlimited'])->default('weekly');
            $table->string('tracking_label'); // e.g. "Cycle + BBT, manual + limited sync"
            $table->json('tracking_integrations')->nullable();

            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
