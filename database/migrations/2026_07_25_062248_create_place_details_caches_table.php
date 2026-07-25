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
        Schema::create('place_details_caches', function (Blueprint $table) {
            $table->foreignId('provider_id')
                ->primary()
                ->constrained('providers')
                ->cascadeOnDelete();

            $table->decimal('rating', 2, 1)->nullable();
            $table->unsignedInteger('review_count')->default(0);
            $table->json('hours_json')->nullable();
            $table->string('business_status')->nullable();

            $table->timestamp('fetched_at')->nullable();
            $table->timestamp('expires_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('place_details_caches');
    }
};
