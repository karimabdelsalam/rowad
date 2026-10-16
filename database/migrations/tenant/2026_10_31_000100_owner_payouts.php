<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner payouts: the owner's bank account, and payout runs that pay many owners their net dues at once
 * (one payment voucher each, plus a bulk-transfer file for the bank).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('owners', function (Blueprint $table) {
            $table->foreignId('bank_id')->nullable()->after('notes')->constrained()->nullOnDelete();
            $table->string('iban', 34)->nullable()->after('bank_id');
            $table->string('bank_beneficiary')->nullable()->after('iban'); // account holder name, when not the owner's own
        });

        Schema::create('owner_payouts', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->date('issued_at');
            $table->date('dues_until');                     // owner entries due on or before this date
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete(); // paid from
            $table->string('status', 20)->default('prepared')->index(); // prepared, transferred, cancelled
            $table->unsignedInteger('owners_count')->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->timestamp('transferred_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('owner_payout_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_payout_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_voucher_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('dues', 14, 2);                 // owed to the owner (debit entries)
            $table->decimal('deductions', 14, 2)->default(0); // commission, expenses, taxes (credit entries)
            $table->decimal('amount', 14, 2);               // net transferred
            $table->unsignedInteger('entries_count')->default(0);
            $table->json('entry_ids')->nullable();          // ledger entries this line settled (reopened if the run is cancelled)
            $table->string('beneficiary');
            $table->string('iban', 34);
            $table->string('bank_name')->nullable();
            $table->timestamps();
        });

        (new \Database\Seeders\Tenant\RolesAndPermissionsSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_payout_items');
        Schema::dropIfExists('owner_payouts');
        Schema::table('owners', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_id');
            $table->dropColumn(['iban', 'bank_beneficiary']);
        });
    }
};
