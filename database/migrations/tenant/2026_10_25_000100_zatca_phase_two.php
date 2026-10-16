<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ZATCA integration phase: the office's e-invoicing unit (keys, CSIDs, counter and hash chain),
 * the signed XML and submission result per invoice, credit/debit notes, and the buyer's national address.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zatca_devices', function (Blueprint $table) {
            $table->id();
            $table->string('environment', 20);
            $table->string('serial');                       // EGS serial in the CSR (1-…|2-…|3-uuid)
            $table->string('status', 20)->default('new');   // new, compliance, ready, revoked
            $table->text('private_key');                    // encrypted
            $table->text('csr');
            $table->text('compliance_token')->nullable();
            $table->text('compliance_secret')->nullable();  // encrypted
            $table->string('compliance_request_id')->nullable();
            $table->text('production_token')->nullable();
            $table->text('production_secret')->nullable();  // encrypted
            $table->timestamp('certificate_expires_at')->nullable();
            $table->unsignedBigInteger('counter')->default(0);  // ICV of the last signed document
            $table->string('last_hash')->nullable();             // its hash = the next document's PIH
            $table->json('onboarding_log')->nullable();
            $table->timestamp('onboarded_at')->nullable();
            $table->timestamps();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('kind', 10)->default('invoice')->after('type');   // invoice, credit, debit
            $table->string('billing_reference', 50)->nullable()->after('kind');
            $table->string('reason')->nullable()->after('billing_reference');
            $table->timestamp('issued_at')->nullable()->after('issue_date');
            $table->foreignId('zatca_device_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('zatca_icv')->nullable();
            $table->longText('zatca_xml')->nullable();
            $table->json('zatca_messages')->nullable();
            $table->unsignedSmallInteger('zatca_attempts')->default(0);
            $table->timestamp('zatca_submitted_at')->nullable();
            $table->index('zatca_status');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->json('national_address')->nullable()->after('vat_number');
        });
    }

    public function down(): void
    {
        Schema::table('clients', fn (Blueprint $table) => $table->dropColumn('national_address'));
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['zatca_status']);
            $table->dropConstrainedForeignId('zatca_device_id');
            $table->dropColumn(['kind', 'billing_reference', 'reason', 'issued_at', 'zatca_icv', 'zatca_xml', 'zatca_messages', 'zatca_attempts', 'zatca_submitted_at']);
        });
        Schema::dropIfExists('zatca_devices');
    }
};
