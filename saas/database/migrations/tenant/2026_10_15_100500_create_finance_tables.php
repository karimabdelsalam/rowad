<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_id')->nullable()->constrained()->nullOnDelete();
            $table->string('holder_name');            // legacy fullname
            $table->string('account_number', 50)->nullable();
            $table->string('iban', 34)->nullable();
            $table->string('branch_number', 50)->nullable();
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->decimal('balance', 14, 2)->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        // Legacy statement_in (سند قبض) and statement_out (سند صرف).
        foreach (['receipt_vouchers', 'payment_vouchers'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->id();
                $table->string('number', 50)->nullable()->unique();
                $table->string('calendar', 10)->default('gregorian');
                $table->date('issued_at')->index();
                // Counterparty: owner, client, citizen, employee, government/trade organization.
                $table->nullableMorphs('party');
                $table->string('party_name')->nullable(); // free-text when no linked record
                $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('amount', 14, 2);
                $table->decimal('vat_amount', 14, 2)->default(0);
                $table->string('payment_method', 20)->default('cash'); // cash, cheque, transfer, card
                $table->string('bank_name')->nullable();
                $table->string('cheque_number', 50)->nullable();
                $table->date('cheque_date')->nullable();
                $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
                if ($name === 'payment_vouchers') {
                    $table->foreignId('expense_type_id')->nullable()->constrained()->nullOnDelete();
                } else {
                    $table->foreignId('transaction_type_id')->nullable()->constrained()->nullOnDelete();
                }
                $table->string('reason')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 50)->unique();
            $table->string('type', 20)->default('simplified'); // simplified (B2C), standard (B2B tax invoice)
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->string('customer_vat_number', 20)->nullable();
            $table->string('calendar', 10)->default('gregorian');
            $table->date('issue_date');
            $table->json('items'); // [{description, quantity, unit_price, vat_percent, vat_amount, total}]
            $table->decimal('subtotal', 14, 2);
            $table->decimal('vat_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->text('zatca_qr')->nullable(); // TLV base64 payload
            $table->string('zatca_uuid', 36)->nullable();
            $table->string('zatca_hash')->nullable();
            $table->string('zatca_status', 20)->nullable(); // phase-2 reporting/clearance status
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Legacy payments: every receivable from a renter/buyer (installments, commissions, expenses).
        Schema::create('installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->morphs('contract'); // RentContract | SaleContract
            // App\Enums\InstallmentType: day_rent, month_rent, year_rent_month/quarter/half_year/year,
            // instant_sale, parts_sale, installment_sale, expenses
            $table->string('type', 30)->index();
            $table->string('calendar', 10)->default('gregorian');
            $table->date('due_date')->index();
            $table->decimal('amount', 14, 2);                       // includes buyer commission
            $table->decimal('seller_commission', 14, 2)->default(0); // legacy commission
            $table->decimal('buyer_commission', 14, 2)->default(0);  // legacy commission2
            $table->string('commission_type', 10)->default('money'); // money, percent
            $table->decimal('vat_amount', 14, 2)->default(0);
            $table->decimal('vat_percent', 5, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);       // partial payments
            $table->boolean('is_collected')->default(false)->index(); // legacy gone
            $table->timestamp('collected_at')->nullable();
            $table->unsignedTinyInteger('reminder_stage')->default(0); // legacy alert 15/1/0
            $table->foreignId('receipt_voucher_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_voucher_id')->nullable()->constrained()->nullOnDelete(); // expenses
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        // Legacy transactions: owner ledger (what the office owes owners and vice versa).
        Schema::create('owner_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->nullable()->constrained()->nullOnDelete(); // null = office's own entry (commission2, comp_tax)
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('transaction_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('installment_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('contract');
            $table->string('source', 10)->nullable(); // rent, sell, build
            $table->string('direction', 6);           // debit = owed to owner, credit = deducted from owner
            $table->decimal('amount', 14, 2);
            $table->decimal('vat_amount', 14, 2)->default(0);
            $table->date('due_date')->index();
            $table->boolean('is_settled')->default(false)->index(); // legacy gone
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('receipt_voucher_id')->nullable()->constrained()->nullOnDelete(); // statinid
            $table->foreignId('payment_voucher_id')->nullable()->constrained()->nullOnDelete(); // statoutid
            $table->foreignId('parent_id')->nullable()->constrained('owner_transactions')->cascadeOnDelete();
            $table->timestamps();
        });

        // Legacy bankacc_transactions.
        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // deposit, withdrawal
            $table->decimal('amount', 14, 2);
            $table->nullableMorphs('voucher'); // ReceiptVoucher | PaymentVoucher
            $table->string('description')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });

        // Partial collection history (pays_pending/partpay).
        Schema::create('installment_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('installment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('receipt_voucher_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('paid_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['installment_payments', 'bank_transactions', 'owner_transactions', 'installments',
            'invoices', 'payment_vouchers', 'receipt_vouchers', 'bank_accounts'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
