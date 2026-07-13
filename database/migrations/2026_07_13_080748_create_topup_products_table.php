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
        Schema::create('topup_products', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique(); // e.g. coaching_sessions_20, skin_scans_5

            // Which subscription_usage column this product tops up.
            // e.g. "ai_coaching" -> increments ai_coaching allowance.
            $table->string('metric');

            $table->string('name');
            $table->enum('topup_kind', ['coaching_sessions', 'skin_scans'])->nullable();// e.g. "+20 Coaching Sessions"
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('quantity')->nullable();
            $table->decimal('price', 8, 2);
            $table->char('currency', 3)->default('USD');
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('topup_products');
    }
};
