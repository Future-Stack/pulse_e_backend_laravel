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
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('life_stage_id')->nullable()->constrained('life_stages')->onDelete('cascade');
            $table->foreignId('activity_id')->nullable()->constrained('activities')->onDelete('cascade');
            $table->text('bio')->nullable();            
            $table->text('profile_img')->nullable();
            $table->integer('age')->nullable();
            $table->float('height')->nullable();
            $table->float('weight')->nullable();            
            $table->string('stripe_account_id')->nullable();
            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
