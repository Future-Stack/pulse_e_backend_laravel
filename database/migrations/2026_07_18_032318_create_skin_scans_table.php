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
        Schema::create('skin_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('image_path');
            
            // Core Scores (Overview & Breakdown)
            $table->integer('overall_score'); // e.g., 80
            $table->integer('hydration_score'); // e.g., 72
            $table->integer('redness_score'); // e.g., 22
            $table->integer('texture_score'); // e.g., 84
            $table->integer('glow_index'); // e.g., 68
            $table->integer('pore_health_score'); // e.g., 79
            $table->integer('elasticity_score'); // e.g., 81
            
            // Matrix Labels / Statuses (e.g., Fair, Low, Good)
            $table->string('hydration_status')->default('Fair');
            $table->string('redness_status')->default('Low');
            $table->string('texture_status')->default('Good');
            $table->string('glow_status')->default('Fair');
            $table->string('pore_health_status')->default('Low');
            $table->string('elasticity_status')->default('Good');

            // Insight Paragraph
            $table->text('neumera_insight')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skin_scans');
    }
};
