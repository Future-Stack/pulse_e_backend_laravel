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
            $table->foreignId('topup_product_id')->nullable()->constrained();

            // subscription  = recurring plan billing (drives usage limits)
            // topup         = one-time purchase of extra scans/sessions
            // refund        = reversal of either of the above
            $table->string('type')->comment('subscription', 'topup', 'refund');

            // --- Subscription-specific (null when type = topup) ---
            $table->enum('billing_cycle', ['month', 'year'])->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->string('stripe_subscription_id')->nullable()->unique();

            // --- Top-up-specific (null when type = subscription) ---
            // --- Shared payment/ledger fields ---
            $table->decimal('amount', 8, 2);
            $table->string('stripe_customer_id')->nullable()->index();
            $table->string('stripe_payment_intent_id')->nullable()->index();
            $table->string('stripe_invoice_id')->nullable();

            $table->string('status')->default('pending')->comment('pending,paid,cancel');

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
