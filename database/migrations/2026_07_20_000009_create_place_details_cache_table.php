<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('place_details_cache', function (Blueprint $table) {
            $table->unsignedBigInteger('provider_id')->primary();
            $table->decimal('rating', 2, 1)->nullable();
            $table->integer('review_count')->nullable();
            $table->json('hours_json')->nullable();
            $table->string('business_status', 30)->nullable(); // OPERATIONAL / CLOSED_*
            $table->timestamp('fetched_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // hard 30-day TTL, purge job enforces regardless

            $table->foreign('provider_id')->references('id')->on('providers')->cascadeOnDelete();
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_details_cache');
    }
};
