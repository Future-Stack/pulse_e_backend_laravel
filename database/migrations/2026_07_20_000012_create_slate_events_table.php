<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slate_events', function (Blueprint $table) {
            $table->id();
            $table->timestamp('occurred_at');
            $table->unsignedSmallInteger('metro_id')->nullable();
            $table->unsignedSmallInteger('category_id')->nullable();
            $table->unsignedTinyInteger('marketplace_life_stage_id')->nullable(); // coarse context only
            $table->unsignedBigInteger('provider_id')->nullable();
            $table->tinyInteger('slot_position')->nullable();
            $table->boolean('sponsored')->default(false);
            $table->enum('event_type', ['impression', 'tap', 'call', 'directions', 'website', 'share']);
            $table->char('session_token', 32); // rotating pseudonymous token; never user_id/insight/device ID

            $table->foreign('metro_id')->references('id')->on('metros')->nullOnDelete();
            $table->foreign('category_id')->references('id')->on('provider_categories')->nullOnDelete();
            $table->foreign('marketplace_life_stage_id', 'slate_events_mkt_life_stage_fk')
                ->references('id')->on('marketplace_life_stages')->nullOnDelete();
            $table->foreign('provider_id')->references('id')->on('providers')->nullOnDelete();

            $table->index(['metro_id', 'category_id', 'occurred_at']);
            $table->index('session_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slate_events');
    }
};
