<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Legacy building_locs: the physical building / compound that holds units.
        Schema::create('buildings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('property_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 50)->nullable();           // legacy buildno
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->string('street')->nullable();
            $table->string('zone')->nullable();
            $table->string('plan_number', 50)->nullable();      // legacy planno
            $table->string('plot_number', 50)->nullable();      // legacy platno
            $table->decimal('area', 12, 2)->nullable();         // legacy size (m²)
            $table->unsignedSmallInteger('floors')->nullable();
            $table->string('address')->nullable();              // legacy location
            $table->text('address_details')->nullable();        // legacy loc_details
            $table->decimal('latitude', 10, 7)->nullable();     // legacy gps_location
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Legacy `building`: the rentable / sellable unit (apartment, shop, villa, land...).
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->nullable()->constrained()->nullOnDelete(); // legacy locid
            $table->string('title');
            $table->string('offer_type', 10)->default('rent')->index(); // rent, sale (legacy type)
            $table->string('status', 20)->default('available')->index(); // available, rented, sold, reserved
            $table->boolean('is_active')->default(true);
            $table->foreignId('property_category_id')->nullable()->constrained()->nullOnDelete(); // buildcat
            $table->foreignId('client_category_id')->nullable()->constrained()->nullOnDelete();   // buyercat
            $table->string('number', 50)->nullable();          // buildno
            $table->string('floor', 20)->nullable();
            $table->unsignedSmallInteger('rooms')->nullable();
            $table->decimal('area', 12, 2)->nullable();
            $table->boolean('is_furnished')->default(false);
            $table->unsignedSmallInteger('max_occupants')->nullable(); // legacy max
            $table->foreignId('deed_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('deed_number', 100)->nullable();
            $table->string('plan_number', 50)->nullable();
            $table->string('plot_number', 50)->nullable();
            $table->string('plots')->nullable();
            $table->string('zone')->nullable();
            $table->string('street')->nullable();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->string('address')->nullable();
            $table->text('address_details')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->json('borders')->nullable();               // boundaries per border_type
            $table->text('details')->nullable();

            // Default commercial terms offered for this unit.
            $table->decimal('rent_amount', 14, 2)->nullable();         // rentvalue
            $table->decimal('daily_rent', 14, 2)->nullable();          // rent_day
            $table->decimal('monthly_rent', 14, 2)->nullable();        // rent_month
            $table->string('rent_period', 10)->nullable();             // day, month, year
            $table->string('rent_payment_cycle', 12)->nullable();      // day, month, quarter, half_year, year
            $table->decimal('rent_commission', 14, 2)->nullable();     // rentcomm
            $table->decimal('sale_commission', 14, 2)->nullable();     // salecomm
            $table->boolean('vat_applicable')->default(false);         // tax

            // Recurring property-management fee charged to owners (legacy mcost*).
            $table->decimal('management_fee', 14, 2)->nullable();
            $table->string('management_fee_cycle', 12)->nullable();    // once, month, quarter, half_year, year
            $table->date('management_fee_start_date')->nullable();
            $table->date('management_fee_next_date')->nullable()->index();

            $table->string('calendar', 10)->default('gregorian');
            $table->nullableMorphs('current_contract');                // active rent/sale contract
            $table->timestamps();
            $table->softDeletes();
        });

        // Legacy building.owner JSON {id[], share[], outgoings[]}.
        Schema::create('owner_unit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained()->cascadeOnDelete();
            $table->decimal('share_percent', 5, 2)->default(100);     // share of income
            $table->decimal('expenses_percent', 5, 2)->default(100);  // share of expenses / mgmt fee
            $table->timestamps();
            $table->unique(['unit_id', 'owner_id']);
        });

        // Legacy building.meters JSON: utility meters attached to a unit.
        Schema::create('unit_meters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meter_type_id')->constrained()->cascadeOnDelete();
            $table->string('number', 100);
            $table->timestamps();
        });

        // Legacy building_reports: dated documents/reports about a unit.
        Schema::create('unit_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_report_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->date('issued_at')->nullable();
            $table->timestamps();
        });

        // Legacy custom_fields_cats / custom_fields / custom_fields_data.
        Schema::create('custom_field_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_field_group_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('text'); // text, checkbox, number, date, select
            $table->string('title');
            $table->string('description')->nullable();
            $table->string('suffix')->nullable();
            $table->string('icon', 50)->nullable();
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_field_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();
            $table->unique(['custom_field_id', 'unit_id']);
        });
    }

    public function down(): void
    {
        foreach (['custom_field_values', 'custom_fields', 'custom_field_groups', 'unit_reports',
            'unit_meters', 'owner_unit', 'units', 'buildings'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
