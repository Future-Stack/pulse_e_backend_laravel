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
        Schema::create('ttc_predictions', function (Blueprint $table) {
           $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cycle_id')->constrained('menstrual_cycles')->cascadeOnDelete();

            $table->boolean('surge_active')->default(false);
            $table->string('surge_message')->nullable();
            $table->integer('hours_remaining_estimate')->nullable();

            $table->unsignedTinyInteger('cycle_day')->nullable();
            $table->unsignedTinyInteger('lh_surge_day')->nullable();

            $table->string('priority')->nullable();
            $table->string('label')->nullable();
            $table->text('priority_message')->nullable();

            $table->json('priority_ranges')->nullable();

            $table->boolean('ai_generated')->default(false);
            $table->boolean('ai_fallback')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ttc_predictions');
    }
};
