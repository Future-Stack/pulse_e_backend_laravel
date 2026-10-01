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
        if (Schema::hasTable('user_pregnancies') && !Schema::hasColumn('user_pregnancies', 'ai_data')) {
            Schema::table('user_pregnancies', function (Blueprint $table) {
                $table->json('ai_data')->nullable()->after('notes');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('user_pregnancies') && Schema::hasColumn('user_pregnancies', 'ai_data')) {
            Schema::table('user_pregnancies', function (Blueprint $table) {
                $table->dropColumn('ai_data');
            });
        }
    }
};
