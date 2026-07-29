<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signal_histories', function (Blueprint $table) {

            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('cycle_id')
                ->nullable()
                ->constrained('menstrual_cycles')
                ->nullOnDelete();

            $table->date('log_date');

            /*
            |--------------------------------------------------------------------------
            | Daily Signal Status
            |--------------------------------------------------------------------------
            */

            $table->boolean('calendar_logged')->default(false);

            $table->boolean('bbt_logged')->default(false);

            $table->boolean('opk_logged')->default(false);

            $table->boolean('mucus_logged')->default(false);

            $table->boolean('symptoms_logged')->default(false);

            /*
            |--------------------------------------------------------------------------
            | AI Summary
            |--------------------------------------------------------------------------
            */

            $table->enum('signal_strength', [
                'none',
                'low',
                'medium',
                'high',
            ])->default('none');

            /*
            |--------------------------------------------------------------------------
            | Full AI Response
            |--------------------------------------------------------------------------
            */

            $table->json('signals')->nullable();

            $table->boolean('ai_generated')->default(false);

            $table->boolean('ai_cached')->default(false);

            $table->json('sources')->nullable();

            $table->json('backend_errors')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Optional Human Summary
            |--------------------------------------------------------------------------
            */

            $table->text('status_message')->nullable();

            $table->timestamps();

            $table->unique([
                'user_id',
                'cycle_id',
                'log_date'
            ]);

            $table->index('user_id');

            $table->index('cycle_id');

            $table->index('log_date');

            $table->index('signal_strength');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signal_histories');
    }
};