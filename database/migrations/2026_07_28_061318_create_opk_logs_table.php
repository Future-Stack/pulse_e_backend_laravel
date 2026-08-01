<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opk_logs', function (Blueprint $table) {

            $table->id();

            $table->foreignId('cycle_id')
                ->constrained('menstrual_cycles')
                ->cascadeOnDelete();

            $table->date('log_date');

            $table->enum('result',[
                'negative',
                'low',
                'high',
                'positive',
                'peak'
            ]);

            $table->decimal('lh_value',6,2)->nullable();

            $table->boolean('outside_window')->default(false);

            $table->boolean('affects_prediction')->default(true);

            $table->text('note')->nullable();

            $table->timestamps();

            $table->unique(['cycle_id','log_date']);

            $table->index('result');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opk_logs');
    }
};
