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
        Schema::create('skin_scan_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('skin_scan_id')->constrained('skin_scans')->onDelete('cascade');
            $table->string('icon_type')->default('drop'); // e.g., 'drop', 'moon', 'sun', 'food'
            $table->string('recommendation_text'); // e.g., "Drink 500ml extra water before 3pm"
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('skin_scan_recommendations');
    }
};
