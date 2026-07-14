<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('community_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title')->nullable();
            $table->string('slug')->unique();
            $table->longText('content');
            $table->boolean('is_anonymous')->default(1);
            $table->boolean('is_approved')->default(1);
            $table->json('tags')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('community_post_life_journey', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_post_id')->constrained('community_posts')->onDelete('cascade');
            $table->foreignId('life_journey_id')->constrained('life_journeys')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('community_post_life_journey');
        Schema::dropIfExists('community_posts');
    }
};