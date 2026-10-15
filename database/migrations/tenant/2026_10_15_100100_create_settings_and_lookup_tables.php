<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Legacy `setting` (varname/value). Keys documented in docs/feature-inventory.md §6.
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 50)->default('general')->index();
            $table->string('key', 100)->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('country_code', 2)->default('SA');
            $table->timestamps();
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        // Simple title-only lookup lists (legacy multiadd modules).
        foreach ([
            'property_categories',   // legacy building_cats (buildcats)
            'client_categories',     // legacy buyer_cats (buycats)
            'deed_types',            // legacy deed_types
            'meter_types',           // legacy meter_types
            'banks',                 // legacy banks
            'expense_types',         // legacy outgoings_types (outtypes)
            'government_organizations', // legacy gov_orgs
            'trade_organizations',   // legacy trade_orgs
            'unit_report_categories', // legacy building_reports_cats
        ] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->timestamps();
            });
        }

        // Legacy building_borders: border/boundary definitions with extra attributes.
        Schema::create('border_types', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->json('attributes')->nullable();
            $table->timestamps();
        });

        // Legacy transaction_types: owner ledger entry kinds (income flag added in v5.x).
        Schema::create('transaction_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique(); // rent_share, mancost, comp_tax, owner_tax, commission, expense ...
            $table->string('title');
            $table->boolean('is_income')->default(false);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['transaction_types', 'border_types', 'unit_report_categories', 'trade_organizations',
            'government_organizations', 'expense_types', 'banks', 'meter_types', 'deed_types',
            'client_categories', 'property_categories', 'districts', 'cities', 'settings'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
