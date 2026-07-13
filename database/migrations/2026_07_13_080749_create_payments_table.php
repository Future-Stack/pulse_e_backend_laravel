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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->nullable()->constrained();

            // subscription  = recurring plan billing (drives usage limits)
            // topup         = one-time purchase of extra scans/sessions
            // refund        = reversal of either of the above
            $table->enum('type', ['subscription', 'topup', 'refund']);

            // --- Subscription-specific (null when type = topup) ---
            $table->enum('billing_cycle', ['monthly', 'annual'])->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('canceled_at')->nullable();
            $table->string('stripe_subscription_id')->nullable()->unique();

            // --- Top-up-specific (null when type = subscription) ---
            $table->foreignId('topup_product_id')->nullable()->constrained();
//            $table->unsignedInteger('remaining_quantity')->nullable();
            $table->timestamp('expires_at')->nullable(); // end of billing month purchased in

            // --- Shared payment/ledger fields ---
            $table->decimal('amount', 8, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('stripe_customer_id')->nullable()->index();
            $table->string('stripe_payment_intent_id')->nullable()->index();
            $table->string('stripe_invoice_id')->nullable();

            $table->enum('status', [
                'pending', 'active', 'trialing', 'past_due',
                'canceled', 'failed', 'refunded', 'expired',
            ])->default('pending');

            $table->timestamp('paid_at')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'type', 'status']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
