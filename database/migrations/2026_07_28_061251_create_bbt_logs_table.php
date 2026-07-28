<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bbt_logs', function (Blueprint $table) {

            $table->id();

            $table->foreignId('cycle_id')
                ->constrained('menstrual_cycles')
                ->cascadeOnDelete();

            $table->date('log_date');

            $table->decimal('temperature',5,2);

            $table->enum('unit',['C','F'])->default('F');

            $table->time('logged_at')->nullable();

            $table->boolean('illness')->default(false);
            $table->boolean('poor_sleep')->default(false);
            $table->boolean('alcohol')->default(false);
            $table->boolean('late_wakeup')->default(false);
            $table->boolean('travel')->default(false);

            $table->boolean('is_excluded')->default(false);

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['cycle_id','log_date']);

            $table->index('log_date');
            $table->index('is_excluded');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bbt_logs');
    }
};

