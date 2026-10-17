<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registering properties and units on Ejar from the system: the details Ejar asks for that the office did not
 * keep before (national address, property type and usage, facilities, unit type, finishing, amenities…) are
 * kept as an "Ejar profile" next to the building / unit, so registering again or adding a unit needs no retyping.
 * Owners and clients remember their Ejar entity once verified (a property owner is referenced by it).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buildings', function (Blueprint $table) {
            $table->string('ejar_property_id', 64)->nullable()->after('longitude');
            $table->json('ejar_profile')->nullable()->after('ejar_property_id');
        });
        Schema::table('units', function (Blueprint $table) {
            $table->json('ejar_profile')->nullable()->after('ejar_unit_id');   // the unit's own details
            $table->json('ejar_property')->nullable()->after('ejar_profile'); // a unit without a building is its own property
        });
        foreach (['owners', 'clients'] as $people) {
            Schema::table($people, function (Blueprint $table) {
                $table->string('ejar_entity_id', 64)->nullable();
                $table->string('ejar_verification', 40)->nullable();
                $table->timestamp('ejar_verified_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['owners', 'clients'] as $people) {
            Schema::table($people, fn (Blueprint $table) => $table->dropColumn(['ejar_entity_id', 'ejar_verification', 'ejar_verified_at']));
        }
        Schema::table('units', fn (Blueprint $table) => $table->dropColumn(['ejar_profile', 'ejar_property']));
        Schema::table('buildings', fn (Blueprint $table) => $table->dropColumn(['ejar_property_id', 'ejar_profile']));
    }
};
