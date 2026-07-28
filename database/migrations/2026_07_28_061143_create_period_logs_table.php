<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('period_logs', function (Blueprint $table) {

            $table->id();

            $table->foreignId('cycle_id')
                ->constrained('menstrual_cycles')
                ->cascadeOnDelete();

            $table->date('log_date');

            $table->enum('flow',[
                'spotting',
                'light',
                'medium',
                'heavy'
            ]);

            $table->boolean('clotting')->default(false);

            $table->unsignedTinyInteger('pain_level')
                ->nullable()
                ->comment('0-10');

            $table->boolean('cramps')->default(false);

            $table->boolean('headache')->default(false);

            $table->boolean('fatigue')->default(false);

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['cycle_id','log_date']);

            $table->index('flow');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_logs');
    }
};
