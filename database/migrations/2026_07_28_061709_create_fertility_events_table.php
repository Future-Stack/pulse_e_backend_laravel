<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fertility_events', function (Blueprint $table) {

            $table->id();

            $table->foreignId('cycle_id')
                ->constrained('menstrual_cycles')
                ->cascadeOnDelete();

            $table->date('event_date');

            $table->enum('event_type',[
                'fertile_window_start',
                'fertile_window_end',
                'lh_surge',
                'ovulation_predicted',
                'ovulation_confirmed',
                'implantation_window',
                'missed_period'
            ]);

            $table->unsignedTinyInteger('cycle_day')->nullable();

            $table->enum('source',[
                'calendar',
                'bbt',
                'opk',
                'mucus',
                'ai',
                'combined'
            ])->default('calendar');

            $table->unsignedTinyInteger('priority_level')->default(1);

            $table->text('message')->nullable();

            $table->boolean('is_confirmed')->default(false);

            $table->timestamps();

            $table->index('event_type');
            $table->index('event_date');
            $table->index('priority_level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fertility_events');
    }
};
