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
        Schema::create('awareness_snapshots', function (Blueprint $table) {

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
            | Current Phase
            |--------------------------------------------------------------------------
            */
            $table->string('phase')->nullable();
            $table->string('day_range')->nullable();
            $table->unsignedTinyInteger('current_cycle_day')->nullable();

            $table->text('dominant_hormone_note')->nullable();
            $table->string('energy')->nullable();
            $table->string('skin')->nullable();
            $table->string('mood')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Hormone Levels
            |--------------------------------------------------------------------------
            */
            $table->string('estrogen')->nullable();
            $table->string('progesterone')->nullable();
            $table->string('lh')->nullable();

            $table->boolean('modeled')->default(false);
            $table->string('source')->nullable();
            $table->text('note')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Phase Education
            |--------------------------------------------------------------------------
            */
            $table->text('bbt_note')->nullable();
            $table->text('energy_note')->nullable();
            $table->text('hormone_note')->nullable();
            $table->text('focus_note')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Four Phase Wheel
            |--------------------------------------------------------------------------
            */
            $table->string('current_phase')->nullable();
            $table->json('phases')->nullable();

            /*
            |--------------------------------------------------------------------------
            | AI Metadata
            |--------------------------------------------------------------------------
            */
            $table->boolean('ai_generated')->default(false);
            $table->boolean('ai_cached')->default(false);

            $table->timestamps();

            $table->unique(['user_id', 'cycle_id']);

            $table->index('phase');
            $table->index('current_phase');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('awareness_snapshots');
    }
};