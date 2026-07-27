<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_category', function (Blueprint $table) {
            $table->unsignedBigInteger('provider_id');
            $table->unsignedSmallInteger('category_id');
            $table->enum('source', ['nppes_taxonomy', 'places_match', 'manual']); // provenance of assignment

            $table->primary(['provider_id', 'category_id']);

            $table->foreign('provider_id')->references('id')->on('providers')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('provider_categories')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_category');
    }
};
