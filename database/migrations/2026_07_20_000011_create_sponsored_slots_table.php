<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsored_slots', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('metro_id');
            $table->unsignedSmallInteger('category_id');
            $table->tinyInteger('slot_number'); // 1-3; app-level rule: max 3 active per metro+category
            $table->unsignedBigInteger('provider_id'); // must be status=active; payment never bypasses vetting
            
            // 🛠️ Fix: timestamp -> dateTime (বা nullable timestamp)
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            
            $table->enum('status', ['reserved', 'active', 'expired', 'cancelled'])->default('reserved');
            $table->integer('monthly_rate_cents')->nullable(); // priced off metros.density_tier
            $table->timestamps();

            $table->foreign('metro_id')->references('id')->on('metros')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('provider_categories')->cascadeOnDelete();
            $table->foreign('provider_id')->references('id')->on('providers')->cascadeOnDelete();

            $table->index(['metro_id', 'category_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsored_slots');
    }
};
