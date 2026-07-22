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
                Schema::create('numera_insights', function (Blueprint $table) {

                $table->id();

                $table->foreignId('user_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->string('title')->nullable();

                $table->string('tag')->nullable();

                $table->string('eyebrow')->nullable();

                $table->text('headline')->nullable();

                $table->text('description')->nullable();

                $table->integer('cycle_day')->nullable();

                $table->string('theme')->nullable();

                $table->string('priority')->nullable();


                $table->enum('status',[
                    'pending',
                    'processing',
                    'completed',
                    'failed'
                ])->default('pending');


                $table->timestamps();

            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('numera_insights');
    }
};
