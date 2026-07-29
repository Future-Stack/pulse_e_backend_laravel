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
        Schema::create('blogs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blog_category_id')
                ->constrained('blog_categories')
                ->cascadeOnDelete();

            // Core content
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('short_desc')->nullable();      // short summary shown on cards
            $table->longText('content');               // full article body (rich text/HTML/markdown)
            $table->string('cover_image')->nullable();  // path/URL to hero image

            // Author (kept simple as name+avatar since no separate Author table requested;
            // swap for author_id FK to users table if authors need accounts)
            $table->string('author_name')->nullable();
            $table->string('author_avatar')->nullable();

            // Article meta shown in "Article Stats" box
            $table->unsignedInteger('reading_time')->nullable(); // in minutes
            $table->unsignedInteger('word_count')->nullable();

            // Tags (simple approach: JSON array column; normalize to tags table if reused elsewhere)
            $table->json('tags')->nullable();

            // Flags / listing behavior
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();

            // SEO
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();

            $table->unsignedInteger('views_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blogs');
    }
};
