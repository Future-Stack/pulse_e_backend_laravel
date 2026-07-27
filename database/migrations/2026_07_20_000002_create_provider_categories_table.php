<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_categories', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('slug', 60)->unique(); // e.g. pelvic-floor-pt
            $table->string('display_name', 120);
            $table->enum('vetting_tier', ['medical', 'licensed_nonmedical', 'consumer']);
            $table->boolean('requires_npi')->default(false); // TRUE for medical tier
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_categories');
    }
};
