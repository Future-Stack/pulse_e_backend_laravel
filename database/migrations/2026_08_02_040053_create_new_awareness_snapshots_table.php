<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('new_awareness_snapshots', function (Blueprint $table) {

            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('cycle_id')
                ->nullable()
                ->constrained('menstrual_cycles')
                ->nullOnDelete();

            /*
            |--------------------------------------------------------------------------
            | AI Cycle Awareness Response
            |--------------------------------------------------------------------------
            */

            $table->string('title')->nullable();

            $table->json('cycle_context')->nullable();

            $table->json('current_phase')->nullable();

            $table->json('luteal_phase')->nullable();

            $table->json('hormone_levels')->nullable();

            $table->json('what_to_know')->nullable();

            $table->json('four_phase_cycle')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Complete AI Response
            |--------------------------------------------------------------------------
            */

            $table->json('ai_response')->nullable();

            /*
            |--------------------------------------------------------------------------
            | AI Metadata
            |--------------------------------------------------------------------------
            */

            $table->boolean('ai_generated')->default(false);

            $table->boolean('ai_cached')->default(false);

            $table->timestamps();

            $table->unique([
                'user_id',
                'cycle_id'
            ]);

            $table->index('user_id');
            $table->index('cycle_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('new_awareness_snapshots');
    }
};