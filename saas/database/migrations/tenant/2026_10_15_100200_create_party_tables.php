<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Arabic four-part name + identity document columns shared by person tables. */
    private function person(Blueprint $table): void
    {
        $table->string('first_name');                       // legacy fname
        $table->string('father_name')->nullable();          // legacy fathname
        $table->string('grandfather_name')->nullable();     // legacy lname (legacy display order: fname fathname lname famname)
        $table->string('family_name')->nullable();          // legacy famname
        $table->string('full_name')->index();               // denormalised for search/sort
        $table->string('id_type', 30)->nullable();          // national_id, iqama, passport, cr
        $table->string('id_number', 50)->nullable()->index();
        $table->string('id_source')->nullable();
        $table->date('id_issue_date')->nullable();
        $table->string('nationality', 100)->nullable();
        $table->string('mobile', 30)->nullable()->index();
        $table->string('email')->nullable();
        $table->string('home_phone', 30)->nullable();
        $table->string('work')->nullable();
        $table->string('work_phone', 30)->nullable();
        $table->text('address')->nullable();
    }

    public function up(): void
    {
        Schema::create('owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // owner portal login
            $this->person($table);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Legacy `buyer`: one table for renters (tenants) and buyers, individual or company.
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // client portal login
            $table->foreignId('client_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20)->default('individual'); // individual, company
            $this->person($table);
            $table->string('company_name')->nullable();
            $table->string('company_registration_number', 50)->nullable(); // legacy com_idnumber
            $table->string('company_mobile', 30)->nullable();
            $table->string('vat_number', 20)->nullable();
            $table->json('billing_info')->nullable(); // ZATCA buyer address etc.
            $table->string('calendar', 10)->default('gregorian');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Legacy `citizens`: other individuals that appear on vouchers.
        Schema::create('citizens', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('id_type', 30)->nullable();
            $table->string('id_number', 50)->nullable()->index();
            $table->string('id_source')->nullable();
            $table->date('id_issue_date')->nullable();
            $table->string('nationality', 100)->nullable();
            $table->string('calendar', 10)->default('gregorian');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $this->person($table);
            $table->date('id_expiry_date')->nullable()->index(); // drives visaEnd15 notification
            $table->string('mobile2', 30)->nullable();
            $table->text('address2')->nullable();
            $table->string('education')->nullable();
            $table->string('marital_status', 20)->nullable();
            $table->decimal('salary', 14, 2)->default(0);
            $table->decimal('rent_commission', 8, 2)->default(0);
            $table->decimal('sale_commission', 8, 2)->default(0);
            $table->date('start_date')->nullable();
            $table->string('calendar', 10)->default('gregorian');
            $table->timestamps();
            $table->softDeletes();
        });

        // Legacy company_owners: partners of the office itself with share percentages.
        Schema::create('company_partners', function (Blueprint $table) {
            $table->id();
            $this->person($table);
            $table->decimal('share_percent', 5, 2)->default(0);
            $table->string('city')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->timestamps();
        });

        // Legacy gov_licenses (home/govlicense): office licences with expiry reminders.
        Schema::create('government_licenses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('number')->nullable();
            $table->date('expiry_date')->nullable()->index();
            $table->string('calendar', 10)->default('gregorian');
            $table->boolean('notify_before_expiry')->default(true); // licenseEnd15
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['government_licenses', 'company_partners', 'employees', 'citizens', 'clients', 'owners'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
