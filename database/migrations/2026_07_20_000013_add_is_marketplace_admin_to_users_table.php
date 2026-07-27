<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the flag that gates access to the /admin/* marketplace routes
 * (see EnsureUserIsMarketplaceAdmin middleware). If your app already has a
 * roles/permissions system (Spatie, custom, etc.), skip this migration and
 * point the middleware at that instead — see the note in the middleware file.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_marketplace_admin')->default(false)->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_marketplace_admin');
        });
    }
};
