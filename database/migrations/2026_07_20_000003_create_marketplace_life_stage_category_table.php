<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_life_stage_category', function (Blueprint $table) {
            $table->unsignedTinyInteger('marketplace_life_stage_id');
            $table->unsignedSmallInteger('category_id');
            $table->tinyInteger('display_priority')->default(0); // order of category suggestions within a stage

            $table->primary(['marketplace_life_stage_id', 'category_id']);

            $table->foreign('marketplace_life_stage_id', 'mkt_life_stage_category_stage_fk')
                ->references('id')->on('marketplace_life_stages')->cascadeOnDelete();
            $table->foreign('category_id', 'mkt_life_stage_category_category_fk')
                ->references('id')->on('provider_categories')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_life_stage_category');
    }
};
