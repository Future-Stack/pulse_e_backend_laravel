<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cervical_mucus_logs', function (Blueprint $table) {

            $table->id();

            $table->foreignId('cycle_id')
                ->constrained('menstrual_cycles')
                ->cascadeOnDelete();

            $table->date('log_date');

            $table->enum('consistency',[
                'dry',
                'sticky',
                'creamy',
                'watery',
                'egg_white'
            ]);

            $table->enum('amount',[
                'low',
                'medium',
                'high'
            ])->nullable();

            $table->enum('color',[
                'clear',
                'white',
                'yellow',
                'cloudy'
            ])->nullable();

            $table->decimal('stretch_cm',4,1)->nullable();

            $table->unsignedTinyInteger('fertility_score')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['cycle_id','log_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cervical_mucus_logs');
    }
};
