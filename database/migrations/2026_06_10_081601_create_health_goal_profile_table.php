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
        Schema::create('health_goal_profile', function (Blueprint $table) {
            $table->id();

            $table->foreignId('health_goal_id')
                ->constrained('health_goals')
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
        Schema::dropIfExists('health_goal_profile');
    }
};
