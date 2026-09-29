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
        Schema::create('life_arc_snapshots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->date('snapshot_date');

            $table->json('milestones')->nullable();
            $table->json('timeline_summary')->nullable();

            $table->string('last_updated_ai')->nullable();

            $table->timestamps();

            // Unique constraint: one snapshot record per user per day
            $table->unique(['user_id', 'snapshot_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('life_arc_snapshots');
    }
};
