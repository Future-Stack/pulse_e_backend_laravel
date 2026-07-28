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

            $table->foreignId('cycle_id')
                ->constrained('menstrual_cycles')
                ->cascadeOnDelete();

            $table->date('log_date');

            $table->boolean('calendar_logged')->default(false);

            $table->boolean('bbt_logged')->default(false);

            $table->boolean('opk_logged')->default(false);

            $table->boolean('mucus_logged')->default(false);

            $table->boolean('symptoms_logged')->default(false);

            $table->enum('signal_strength',[
                'none',
                'low',
                'medium',
                'high'
            ])->default('none');

            $table->text('status_message')->nullable();

            $table->timestamps();

            $table->unique(['cycle_id','log_date']);

            $table->index('signal_strength');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signal_histories');
    }
};

