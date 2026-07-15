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

            $table->string('name');
            $table->text('description');
            $table->enum('topup_kind', ['coaching_sessions', 'skin_scans'])->nullable();// e.g. "+20 Coaching Sessions"
            $table->integer('limit')->nullable();
            $table->decimal('price', 8, 2);
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
