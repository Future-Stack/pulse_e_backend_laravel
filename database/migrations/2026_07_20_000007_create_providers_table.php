<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->char('npi', 10)->nullable()->unique(); // NULL for consumer-tier businesses
            $table->string('google_place_id')->nullable()->index(); // storable indefinitely per Google ToS
            $table->string('display_name', 160);
            $table->string('org_name', 160)->nullable();
            $table->string('phone_e164', 20)->nullable(); // normalized; used in NPPES<->Places join
            $table->string('website')->nullable();

            // normalized address (libpostal or equivalent) before matching
            $table->string('addr_line1')->nullable();
            $table->string('addr_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip')->nullable();

            // 🛠️ Fixed: point() -> geometry() এবং spatialIndex-এর জন্য nullable() উঠিয়ে দেওয়া হয়েছে
            $table->geometry('location', subtype: 'point', srid: 4326);
            
            $table->unsignedSmallInteger('metro_id')->nullable();

            $table->boolean('source_nppes')->default(false);
            $table->boolean('source_places')->default(false);
            $table->decimal('match_confidence', 3, 2)->nullable(); // fuzzy-join score; below threshold -> manual review

            $table->enum('status', ['candidate', 'vetted', 'active', 'suspended', 'excluded'])
                ->default('candidate');

            $table->timestamps();

            // Foreign key & Indexes
            $table->foreign('metro_id')->references('id')->on('metros')->nullOnDelete();
            $table->spatialIndex('location');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
