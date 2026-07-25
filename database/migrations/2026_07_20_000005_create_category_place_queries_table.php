<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_place_queries', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('category_id');
            $table->string('places_type', 60)->nullable(); // e.g. doctor, spa, gym
            $table->string('keyword', 120); // Text Search keyword
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('provider_categories')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_place_queries');
    }
};
