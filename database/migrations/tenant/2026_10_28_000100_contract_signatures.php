<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Electronic signature of rent contracts: one row per party (each owner and each tenant), reached by a
 * private link and confirmed with a code sent to their own mobile. The signature image, time, IP and a
 * fingerprint of the contract terms they saw are kept as evidence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_signatures', function (Blueprint $table) {
            $table->id();
            $table->morphs('signable');                         // the contract
            $table->nullableMorphs('signer');                   // owner or client
            $table->string('role', 10);                         // lessor, lessee
            $table->string('name');
            $table->string('mobile', 30)->nullable();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->string('status', 10)->default('pending');    // pending, signed, declined
            $table->string('otp_hash')->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->unsignedTinyInteger('otp_attempts')->default(0);
            $table->longText('signature')->nullable();          // PNG data URI drawn by the signer
            $table->string('document_hash', 64)->nullable();    // fingerprint of the terms at signing
            $table->timestamp('signed_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('decline_reason')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('rent_contracts', fn (Blueprint $table) => $table->timestamp('signed_at')->nullable()->after('ejar_notes'));

        (new \Database\Seeders\Tenant\TemplateSeeder)->run(); // the signing link and code messages
    }

    public function down(): void
    {
        Schema::table('rent_contracts', fn (Blueprint $table) => $table->dropColumn('signed_at'));
        Schema::dropIfExists('contract_signatures');
    }
};
