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
        Schema::create('cycle_statistics', function (Blueprint $table) {

            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedTinyInteger('completed_cycles')->default(0);

            $table->unsignedTinyInteger('average_cycle_length')->default(28);

            $table->unsignedTinyInteger('average_period_length')->default(5);

            $table->unsignedTinyInteger('average_ovulation_day')->nullable();

            $table->unsignedTinyInteger('cycle_variance_days')->default(0);

            $table->enum('reliability_level', [
                'low',
                'medium',
                'high'
            ])->default('low');

            $table->decimal('reliability_score',5,2)
                ->default(0);

            $table->date('last_period_date')->nullable();

            $table->date('predicted_next_period')->nullable();

            $table->timestamps();

            $table->unique('user_id');

            $table->index('reliability_level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cycle_statistics');
    }
};
