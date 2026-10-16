<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** E-collection: a pay link per installment and what the gateway said about it. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installment_id')->constrained()->cascadeOnDelete();
            $table->string('token', 48)->unique();           // the public pay link
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('SAR');
            $table->string('gateway', 20);                   // moyasar
            $table->string('gateway_id')->nullable()->unique(); // the gateway's payment id once one exists
            $table->string('status', 20)->default('initiated')->index(); // initiated, paid, failed, superseded
            $table->string('source', 40)->nullable();        // creditcard / applepay / stcpay (+ brand)
            $table->string('payer_name')->nullable();
            $table->string('payer_mobile', 30)->nullable();
            $table->foreignId('receipt_voucher_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('failure', 500)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_payments');
    }
};
