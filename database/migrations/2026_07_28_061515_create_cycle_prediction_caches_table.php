<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cycle_prediction_caches', function (Blueprint $table) {

            $table->id();

            $table->foreignId('cycle_id')
                ->constrained('menstrual_cycles')
                ->cascadeOnDelete();

            $table->string('cache_key')->unique();

            $table->string('endpoint');

            $table->json('request_payload')->nullable();

            $table->json('prediction');

            $table->string('prediction_version')->nullable();

            $table->boolean('ai_generated')->default(true);

            $table->boolean('ai_cached')->default(true);

            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index('endpoint');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_prediction_caches');
    }
};
