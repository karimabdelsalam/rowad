<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();  // managerid
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete(); // tomanagerid
            $table->text('content');
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamps();
        });

        // Dashboard personal notes (legacy notes).
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->timestamps();
        });

        // Legacy support: tickets from owner/client portals.
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique(); // prefix from settings.support_ticket_prefix
            $table->string('type', 20); // request, enquiry, problem, suggestion
            $table->foreignId('owner_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject');
            $table->text('message');
            $table->string('status', 20)->default('open')->index(); // open, answered, closed
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('support_ticket_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('message');
            $table->timestamps();
        });

        // Legacy inbox: contact-form messages from the public site.
        Schema::create('inbox_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('mobile', 30)->nullable();
            $table->string('subject')->nullable();
            $table->text('message');
            $table->timestamp('read_at')->nullable()->index();
            $table->text('reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();
        });

        // Legacy print_samples (module "sample"): editable HTML document templates.
        Schema::create('print_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique(); // rent_contract, sale_contract, receipt_voucher, invoice, letter_*...
            $table->string('title');
            $table->longText('header')->nullable();
            $table->longText('content');
            $table->longText('footer')->nullable();
            $table->timestamps();
        });

        // Supports "restore previous version" (sample/restore).
        Schema::create('print_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('print_template_id')->constrained()->cascadeOnDelete();
            $table->longText('header')->nullable();
            $table->longText('content');
            $table->longText('footer')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        // Legacy email_templates: one row per notification event, email + SMS bodies.
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('event', 50)->unique(); // payRent15, sellPay1, newRentContract, otpReset ...
            $table->string('title');
            $table->string('email_subject')->nullable();
            $table->longText('email_body')->nullable();
            $table->text('sms_body')->nullable();
            $table->boolean('email_enabled')->default(true);
            $table->boolean('sms_enabled')->default(false);
            $table->timestamps();
        });

        // Legacy punish: employee penalties / deductions.
        Schema::create('employee_penalties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('reason', 50)->nullable();
            $table->text('reason_text')->nullable();
            $table->unsignedTinyInteger('for_month');
            $table->unsignedSmallInteger('for_year');
            $table->string('calendar', 10)->default('gregorian');
            $table->date('issued_at');
            $table->timestamps();
        });

        // Legacy pages: CMS pages / menu links for the public site.
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->string('position', 20)->default('header'); // header, footer, sidebar
            $table->string('type', 20)->default('content');    // content, internal_link, external_link
            $table->string('title');
            $table->string('slug')->nullable()->unique();
            $table->longText('content')->nullable();
            $table->string('link')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['pages', 'employee_penalties', 'message_templates', 'print_template_versions', 'print_templates',
            'inbox_messages', 'support_ticket_replies', 'support_tickets', 'notes', 'tasks'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
