<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intercourse_logs', function (Blueprint $table) {

            $table->id();

            $table->foreignId('cycle_id')
                ->constrained('menstrual_cycles')
                ->cascadeOnDelete();

            $table->date('log_date');

            $table->boolean('protected')->default(false);

            $table->boolean('ejaculation')->default(true);

            $table->boolean('inside_fertile_window')->default(false);

            $table->boolean('trying_to_conceive')->default(true);

            $table->unsignedTinyInteger('cycle_day')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['cycle_id','log_date']);

            $table->index('log_date');
            $table->index('trying_to_conceive');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intercourse_logs');
    }
};
