<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name_ar');
            $table->string('name_en');
            $table->text('description')->nullable();
            $table->decimal('monthly_price', 10, 2)->default(0);
            $table->decimal('yearly_price', 10, 2)->default(0);
            $table->string('currency', 3)->default('SAR');
            // Quotas; null means unlimited. Replaces legacy updatecore plan limits.
            $table->unsignedInteger('max_units')->nullable();
            $table->unsignedInteger('max_users')->nullable();
            $table->unsignedInteger('max_storage_mb')->nullable();
            $table->unsignedInteger('max_sms_per_month')->nullable();
            $table->json('features')->nullable(); // e.g. ["owner_portal","client_portal","zatca","api"]
            $table->unsignedSmallInteger('trial_days')->default(14);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('plan_id')->constrained();
            $table->string('billing_cycle')->default('monthly'); // monthly, yearly
            $table->string('status')->default('active')->index(); // active, past_due, cancelled, expired
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('SAR');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('gateway')->nullable(); // moyasar, tap, paytabs, manual
            $table->string('gateway_reference')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->cascadeOnDelete();
        });

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->decimal('vat_amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('SAR');
            $table->string('status')->default('pending'); // pending, paid, failed, refunded
            $table->string('gateway')->nullable();
            $table->string('gateway_reference')->nullable()->index();
            $table->json('gateway_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
