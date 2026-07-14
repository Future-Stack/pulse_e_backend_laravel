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
        Schema::create('health_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->date('log_date');

            // Emoji value
            // 😔 😟 😬 🙂 😊
            $table->string('mood', 10);

            $table->enum('energy_level', [
                'Very Low',
                'Low',
                'Moderate',
                'High',
                'Very High'
            ]);

            // Example:
            // ["Headache","Fatigue","Nausea"]
            $table->json('symptoms')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'log_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('health_logs');
    }
};