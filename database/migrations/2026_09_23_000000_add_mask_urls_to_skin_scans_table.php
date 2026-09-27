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
            $table->json('mask_urls')->nullable()->after('neumera_insight');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('skin_scans', function (Blueprint $table) {
            $table->dropColumn('mask_urls');
        });
    }
};
