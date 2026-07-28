<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pregnancy_test_logs', function (Blueprint $table) {

            $table->id();

            $table->foreignId('cycle_id')
                ->constrained('menstrual_cycles')
                ->cascadeOnDelete();

            $table->date('test_date');

            $table->enum('result',[
                'negative',
                'positive',
                'invalid'
            ]);

            $table->string('brand')->nullable();

            $table->enum('test_time',[
                'morning',
                'afternoon',
                'evening',
                'night'
            ])->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('result');
            $table->index('test_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pregnancy_test_logs');
    }
};
