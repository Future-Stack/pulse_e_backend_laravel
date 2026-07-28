<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_histories', function (Blueprint $table) {

            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->enum('type', [
                'period_reminder',
                'fertility_reminder',
                'bbt_reminder',
                'opk_reminder',
                'ovulation_reminder',
                'general'
            ]);

            $table->string('title');

            $table->text('message');

            $table->timestamp('scheduled_at')->nullable();

            $table->timestamp('sent_at')->nullable();

            $table->timestamp('read_at')->nullable();

            $table->enum('status', [
                'pending',
                'sent',
                'delivered',
                'read',
                'failed'
            ])->default('pending');

            $table->timestamps();

            $table->index(['user_id','status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_histories');
    }
};

