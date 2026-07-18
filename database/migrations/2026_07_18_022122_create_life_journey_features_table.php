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
        Schema::create('life_journey_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('life_journey_id')->constrained('life_journeys')->onDelete('cascade');
            $table->string('feature_name'); // e.g., "Skin Health Tracking", "Ovulation Prediction"
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('life_journey_features');
    }
};
