<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metros', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('name', 80); // e.g. Salt Lake City
            $table->char('state', 2);
            $table->geometry('centroid', subtype: 'point', srid: 4326);
            
            $table->smallInteger('radius_km');
            $table->tinyInteger('density_tier')->default(1); // Maps to slot pricing tiers
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metros');
    }
};
