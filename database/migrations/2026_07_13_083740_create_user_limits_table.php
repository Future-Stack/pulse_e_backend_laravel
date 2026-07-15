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
            $table->unsignedInteger('skin_scans_limit')->default(0);
            $table->unsignedInteger('ai_coaching_limit')->default(0);
            $table->unsignedInteger('deep_reports_limit')->default(0);

            // --- Top-up balances (aggregated across active topup purchases) ---
            $table->unsignedInteger('skin_scans_topup_limit')->default(0);
            $table->unsignedInteger('ai_coaching_topup_limit')->default(0);

            $table->timestamp('subscription_expires_at')->nullable();
            $table->timestamp('topup_expires_at')->nullable();
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
