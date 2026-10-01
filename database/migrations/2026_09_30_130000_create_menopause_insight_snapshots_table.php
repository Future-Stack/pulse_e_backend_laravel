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
        Schema::create('menopause_insight_snapshots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->date('snapshot_date');
            $table->string('period', 20)->default('7d');

            $table->json('symptom_matrix')->nullable();
            $table->string('period_selected', 20)->nullable()->default('7d');
            $table->json('tabs')->nullable();
            $table->boolean('journey_active')->default(true);
            $table->text('message')->nullable();

            $table->string('last_updated_ai')->nullable();

            $table->timestamps();

            // Unique constraint: one snapshot per user, date, and period
            $table->unique(['user_id', 'snapshot_date', 'period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menopause_insight_snapshots');
    }
};
