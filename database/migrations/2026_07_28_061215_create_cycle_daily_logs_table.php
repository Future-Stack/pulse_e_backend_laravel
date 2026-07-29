<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cycle_daily_logs', function (Blueprint $table) {

            $table->id();

            $table->foreignId('cycle_id')
                ->constrained('menstrual_cycles')
                ->cascadeOnDelete();

            $table->date('log_date');

            $table->unsignedTinyInteger('cycle_day');

            $table->enum('phase',[
                'menstrual',
                'follicular',
                'ovulatory',
                'luteal'
            ]);

            $table->enum('tag',[
                'none',
                'period',
                'fertile',
                'ovulation'
            ])->default('none');

            $table->boolean('is_prediction')->default(true);

            $table->timestamps();

            $table->unique(['cycle_id','log_date']);

            $table->index('phase');
            $table->index('tag');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_daily_logs');
    }
};

