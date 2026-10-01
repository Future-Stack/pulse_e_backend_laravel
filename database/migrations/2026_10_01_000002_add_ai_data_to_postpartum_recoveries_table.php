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
        if (Schema::hasTable('postpartum_recoveries') && !Schema::hasColumn('postpartum_recoveries', 'ai_data')) {
            Schema::table('postpartum_recoveries', function (Blueprint $table) {
                $table->json('ai_data')->nullable()->after('notes');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('postpartum_recoveries') && Schema::hasColumn('postpartum_recoveries', 'ai_data')) {
            Schema::table('postpartum_recoveries', function (Blueprint $table) {
                $table->dropColumn('ai_data');
            });
        }
    }
};
