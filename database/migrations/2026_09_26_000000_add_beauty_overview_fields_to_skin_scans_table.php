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
        Schema::table('skin_scans', function (Blueprint $table) {
            $table->string('status_label')->nullable()->after('elasticity_status');
            $table->integer('score_change')->default(0)->after('status_label');
            $table->string('comparison_text')->nullable()->after('score_change');
            $table->json('findings')->nullable()->after('comparison_text');
            $table->json('correlations')->nullable()->after('findings');
            $table->json('ai_insights')->nullable()->after('correlations');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('skin_scans', function (Blueprint $table) {
            $table->dropColumn([
                'status_label',
                'score_change',
                'comparison_text',
                'findings',
                'correlations',
                'ai_insights',
            ]);
        });
    }
};
