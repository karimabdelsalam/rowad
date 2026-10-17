<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ejar API (External Contract Registration Service): each office connects its own Ejar brokerage account.
 * Units remember which Ejar property / unit they are; contracts remember their Ejar draft, version, state and
 * how far the registration got, so a failed step can be retried without duplicating what Ejar already has.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->string('ejar_property_id', 64)->nullable()->after('deed_number');
            $table->string('ejar_unit_id', 64)->nullable()->after('ejar_property_id');
        });
        Schema::table('clients', function (Blueprint $table) {
            // Ejar identifies a company tenant by its CR number and registration date
            $table->date('company_registration_date')->nullable()->after('company_registration_number');
        });
        Schema::table('rent_contracts', function (Blueprint $table) {
            $table->string('ejar_contract_id', 64)->nullable()->after('ejar_number');
            $table->unsignedInteger('ejar_version')->nullable()->after('ejar_contract_id');
            $table->string('ejar_state', 40)->nullable()->after('ejar_version');   // as Ejar reports it
            $table->json('ejar_progress')->nullable()->after('ejar_state');        // registration steps already done
            $table->text('ejar_error')->nullable()->after('ejar_progress');
            $table->timestamp('ejar_synced_at')->nullable()->after('ejar_error');
        });
    }

    public function down(): void
    {
        Schema::table('rent_contracts', fn (Blueprint $table) => $table->dropColumn(['ejar_contract_id', 'ejar_version', 'ejar_state', 'ejar_progress', 'ejar_error', 'ejar_synced_at']));
        Schema::table('clients', fn (Blueprint $table) => $table->dropColumn('company_registration_date'));
        Schema::table('units', fn (Blueprint $table) => $table->dropColumn(['ejar_property_id', 'ejar_unit_id']));
    }
};
