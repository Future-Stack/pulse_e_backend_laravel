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
        Schema::create('life_journey_profile', function (Blueprint $table) {
            $table->id();
            $table->foreignId('life_journey_id')
                ->constrained('life_journeys')
                ->onDelete('cascade');

            $table->foreignId('profile_id')
                ->constrained('profiles')
                ->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('life_journey_profile');
    }
};
