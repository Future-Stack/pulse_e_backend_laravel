<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vetting_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('provider_id');
            $table->enum('check_type', ['license', 'leie', 'disciplinary', 'certification', 'reputation']);
            $table->enum('status', ['pass', 'fail', 'pending', 'expired']);
            $table->string('evidence_url')->nullable(); // board lookup URL, screenshot ref, etc.
            $table->text('notes')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('next_due_at')->nullable(); // drives re-verification queue
            $table->string('checked_by', 80)->nullable(); // job name or admin user
            $table->timestamps();

            $table->foreign('provider_id')->references('id')->on('providers')->cascadeOnDelete();
            $table->index(['provider_id', 'check_type']);
            $table->index('next_due_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vetting_records');
    }
};
