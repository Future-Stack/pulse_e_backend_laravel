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
        Schema::create('user_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Points at the active subscription row in `payments` this cycle's
            // usage belongs to. Nullable for free-plan users with no payment row.
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();

            // --- Usage against the plan limit, current cycle only ---
            $table->unsignedInteger('skin_scans_used')->default(0);
            $table->unsignedInteger('ai_coaching_used')->default(0);
            $table->unsignedInteger('deep_reports_used')->default(0);

            // --- Top-up balances (aggregated across active topup purchases) ---
            $table->unsignedInteger('skin_scans_topup_balance')->default(0);
            $table->unsignedInteger('ai_coaching_topup_balance')->default(0);

            // --- The two expiry dates you asked to track ---
            // Mirrors payments.current_period_end for the active subscription;
            // drives when skin_scans_used/ai_coaching_used/deep_reports_used reset to 0.
            $table->timestamp('subscription_expires_at')->nullable();
            // Shared expiry for whichever topup batch is currently active
            // (topups always expire end of the billing month purchased in).
            $table->timestamp('topup_expires_at')->nullable();

            $table->timestamps();

            $table->index('subscription_expires_at');
            $table->index('topup_expires_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_limits');
    }
};
