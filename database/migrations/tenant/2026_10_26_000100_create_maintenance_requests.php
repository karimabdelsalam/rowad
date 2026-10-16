<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maintenance requests: reported by the tenant (with photos) or by staff, assigned to a technician,
 * approved by the owner above the office's limit, and billed through a payment voucher to the
 * owners, the tenant's contract or the office.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_requests', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rent_contract_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();   // the tenant who reported it
            $table->string('category', 20)->default('other');
            $table->string('priority', 10)->default('normal');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('preferred_time')->nullable();                               // when the tenant is home
            $table->string('status', 20)->default('new')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('technician_name')->nullable();
            $table->string('technician_phone', 30)->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->decimal('estimated_cost', 14, 2)->nullable();
            $table->string('owner_decision', 10)->nullable();                            // approved, rejected
            $table->foreignId('owner_decided_by')->nullable()->constrained('owners')->nullOnDelete();
            $table->timestamp('owner_decided_at')->nullable();
            $table->string('owner_note')->nullable();
            $table->decimal('cost', 14, 2)->nullable();
            $table->string('charge_to', 10)->nullable();                                 // owners, contract, office
            $table->foreignId('payment_voucher_id')->nullable()->constrained()->nullOnDelete();
            $table->text('resolution')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('feedback', 1000)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // the new permissions and message templates in offices that are already running
        (new \Database\Seeders\Tenant\RolesAndPermissionsSeeder)->run();
        (new \Database\Seeders\Tenant\TemplateSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_requests');
    }
};
