<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bbt_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('bbt_logs', 'coverline_value')) {
                $table->decimal('coverline_value', 5, 2)->nullable()->after('notes');
            }
            if (! Schema::hasColumn('bbt_logs', 'ovulation_confirmed')) {
                $table->boolean('ovulation_confirmed')->default(false)->after('coverline_value');
            }
            if (! Schema::hasColumn('bbt_logs', 'cycle_day')) {
                $table->integer('cycle_day')->nullable()->after('ovulation_confirmed');
            }
            if (! Schema::hasColumn('bbt_logs', 'phase')) {
                $table->string('phase')->nullable()->after('cycle_day');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bbt_logs', function (Blueprint $table) {
            $table->dropColumn(['coverline_value', 'ovulation_confirmed', 'cycle_day', 'phase']);
        });
    }
};
