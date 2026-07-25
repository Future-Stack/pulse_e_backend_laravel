<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_taxonomy_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('category_id');
            $table->char('nucc_code', 10)->index(); // e.g. 207VE0102X
            $table->boolean('nucc_prefix')->default(false); // supports prefix wildcard matching
            $table->string('label', 160); // human-readable taxonomy label
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('provider_categories')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_taxonomy_codes');
    }
};
