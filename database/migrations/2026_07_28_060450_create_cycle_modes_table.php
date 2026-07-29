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
        Schema::create('cycle_modes', function (Blueprint $table) {

            $table->id();

            /*
            |--------------------------------------------------------------------------
            | User
            |--------------------------------------------------------------------------
            */
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Current Cycle Mode
            |--------------------------------------------------------------------------
            */
            $table->enum('mode', [
                'cycle_awareness',
                'trying_to_conceive',
                'avoiding_pregnancy',
            ])->default('cycle_awareness');

            /*
            |--------------------------------------------------------------------------
            | Mode Status
            |--------------------------------------------------------------------------
            */
            $table->boolean('is_active')->default(true);
            $table->timestamp('activated_at')->nullable();

            /*
            |--------------------------------------------------------------------------
            | Avoiding Pregnancy Consent
            |--------------------------------------------------------------------------
            */
            $table->boolean('has_consented')->default(false);
            $table->string('consent_version')->nullable();
            $table->timestamp('consented_at')->nullable();

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */
            $table->unique('user_id');
            $table->index('mode');
            $table->index('has_consented');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cycle_modes');
    }
};