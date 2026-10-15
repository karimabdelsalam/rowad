<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Gap-free sequential numbers for contracts, vouchers and invoices (ZATCA requires unique invoice numbers).
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50);       // receipt_voucher, payment_voucher, invoice, rent_contract ...
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
            $table->unique(['key', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
