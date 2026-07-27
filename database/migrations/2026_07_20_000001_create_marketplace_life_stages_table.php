<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NOTE: named marketplace_life_stages (not life_stages) because this app
 * already has its own life_stages table/model (health-domain "life journey"
 * onboarding concept, unrelated to this spec's Part 1 taxonomy). Keeping the
 * marketplace's six spec life-stage slugs in a separate table avoids a
 * table-name AND Eloquent-model-class collision with the existing
 * App\Models\LifeStage. See MarketplaceLifeStage model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_life_stages', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('slug', 40)->unique(); // e.g. beauty-radiance
            $table->string('name', 80); // Display name
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_life_stages');
    }
};
