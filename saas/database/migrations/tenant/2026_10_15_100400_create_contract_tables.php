<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Legacy contract_rights (module "rights"): reusable contract clauses.
        Schema::create('contract_terms', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('content');
            $table->string('cycle', 12)->nullable(); // applies to: rent, sale, both / payment cycle
            $table->timestamps();
        });

        Schema::create('rent_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('number', 50)->nullable()->unique();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete(); // renter (legacy buyer)
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete(); // agent for commission
            $table->string('calendar', 10)->default('gregorian');
            $table->date('contract_date');                       // postdate
            $table->date('start_date');
            $table->date('end_date');
            $table->string('period_unit', 10);                   // day, month, year (legacy period)
            $table->unsignedSmallInteger('period_count');        // periodnum
            $table->string('payment_cycle', 12);                 // day, month, quartyear, midyear, year
            $table->decimal('rent_amount', 14, 2);               // amount per payment cycle (rentvalue)
            $table->decimal('yearly_rent', 14, 2)->nullable();
            $table->decimal('commission', 14, 2)->default(0);
            $table->string('commission_cycle', 12)->nullable();  // once, day, month, quartyear, midyear, year
            $table->boolean('vat_applicable')->default(false);
            $table->decimal('vat_percent', 5, 2)->default(0);
            $table->string('usage', 20)->nullable();             // residential, commercial (usein)
            $table->json('meter_readings')->nullable();          // meters at handover
            $table->longText('extra_terms')->nullable();         // extrarights
            $table->text('notes')->nullable();
            $table->date('next_payment_date')->nullable();
            $table->decimal('next_payment_amount', 14, 2)->nullable();
            $table->string('status', 20)->default('active')->index(); // active, ended, cancelled
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('archived_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sale_contracts', function (Blueprint $table) {
            $table->id();
            $table->string('number', 50)->nullable()->unique();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete(); // buyer
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('calendar', 10)->default('gregorian');
            $table->date('contract_date');
            $table->decimal('total_amount', 14, 2);
            $table->string('payment_type', 12);  // once, parts, installment
            // once: {commission, commission_type money|percent, commission_payer buyer|seller|split}
            // parts: [{amount, commission, cheque_number, received_date, due_date, deferred}]
            // installment: {first_payment, count, cycle, start_date}
            $table->json('payment_plan');
            $table->longText('extra_terms')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('active')->index(); // active, completed, cancelled
            $table->timestamp('archived_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        // Legacy rightsid lists on rent/sell contracts.
        Schema::create('contract_term_selections', function (Blueprint $table) {
            $table->id();
            $table->morphs('contract');
            $table->foreignId('contract_term_id')->constrained()->cascadeOnDelete();
            $table->unique(['contract_type', 'contract_id', 'contract_term_id'], 'contract_term_unique');
        });

        // Legacy letters: official letters (rent raise, renewal review, cancellation notices...).
        Schema::create('letters', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30)->index();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('calendar', 10)->default('gregorian');
            $table->date('letter_date');
            $table->boolean('is_official')->default(true);
            $table->decimal('rent_raise_amount', 14, 2)->nullable();
            $table->json('data')->nullable(); // review/cancel flags and template variables
            $table->boolean('marks_unit_reserved')->default(false); // makeBuildingBusy
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['letters', 'contract_term_selections', 'sale_contracts', 'rent_contracts', 'contract_terms'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
