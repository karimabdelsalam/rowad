<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR module (Saudi labour law): pay structure and GOSI on the employee, leave requests,
 * salary advances, monthly payroll runs with their lines, and end-of-service settlements.
 * `employees.salary` keeps its legacy meaning (basic salary); allowances are new columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('job_title')->nullable()->after('work');
            $table->string('department')->nullable()->after('job_title');
            $table->string('employment_type', 20)->default('full_time')->after('department'); // full_time, part_time, contract
            $table->string('status', 20)->default('active')->index()->after('employment_type'); // active, on_leave, terminated
            $table->date('contract_end_date')->nullable()->after('start_date');
            $table->date('termination_date')->nullable()->after('contract_end_date');
            $table->decimal('housing_allowance', 14, 2)->default(0)->after('salary');
            $table->decimal('transport_allowance', 14, 2)->default(0)->after('housing_allowance');
            $table->decimal('other_allowances', 14, 2)->default(0)->after('transport_allowance');
            $table->boolean('gosi_subscribed')->default(true)->after('other_allowances');
            $table->string('gosi_number', 30)->nullable()->after('gosi_subscribed');
            $table->foreignId('bank_id')->nullable()->after('gosi_number')->constrained('banks')->nullOnDelete();
            $table->string('iban', 34)->nullable()->after('bank_id');
            $table->unsignedSmallInteger('annual_leave_days')->nullable()->after('iban'); // null: by law (21, 30 after 5 years)
            $table->unsignedTinyInteger('weekly_hours')->default(48)->after('annual_leave_days');
        });

        Schema::table('banks', function (Blueprint $table) {
            $table->string('swift', 11)->nullable()->after('title'); // BIC, needed in the WPS salary file
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // annual, sick, unpaid, maternity, paternity, marriage, bereavement, hajj, other
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('days');
            $table->string('status', 20)->default('pending')->index(); // pending, approved, rejected, cancelled
            $table->text('reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('calendar', 10)->default('gregorian');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'start_date']);
        });

        Schema::create('employee_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->decimal('installment_amount', 14, 2);
            $table->decimal('remaining', 14, 2);
            $table->date('issued_at');
            $table->text('notes')->nullable();
            $table->foreignId('payment_voucher_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('status', 20)->default('draft')->index(); // draft, approved, paid
            $table->decimal('total_gross', 14, 2)->default(0);
            $table->decimal('total_deductions', 14, 2)->default(0);
            $table->decimal('total_net', 14, 2)->default(0);
            $table->decimal('total_gosi_employer', 14, 2)->default(0);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['year', 'month']);
        });

        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('basic', 14, 2)->default(0);
            $table->decimal('housing', 14, 2)->default(0);
            $table->decimal('transport', 14, 2)->default(0);
            $table->decimal('other_allowances', 14, 2)->default(0);
            $table->decimal('overtime', 14, 2)->default(0);
            $table->decimal('bonus', 14, 2)->default(0);
            $table->decimal('penalties', 14, 2)->default(0);
            $table->decimal('advances', 14, 2)->default(0);
            $table->decimal('unpaid_leave', 14, 2)->default(0);
            $table->unsignedTinyInteger('unpaid_leave_days')->default(0);
            $table->decimal('gosi_employee', 14, 2)->default(0);
            $table->decimal('gosi_employer', 14, 2)->default(0);
            $table->decimal('other_deductions', 14, 2)->default(0);
            $table->decimal('gross', 14, 2)->default(0);
            $table->decimal('net', 14, 2)->default(0);
            $table->foreignId('payment_voucher_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['payroll_run_id', 'employee_id']);
        });

        Schema::create('end_of_service_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('termination_date');
            $table->string('reason', 30); // resignation, termination, contract_end, retirement, death, marriage_or_birth, force_majeure
            $table->decimal('service_years', 6, 2)->default(0);
            $table->decimal('last_wage', 14, 2)->default(0);
            $table->decimal('award', 14, 2)->default(0);
            $table->decimal('leave_balance_days', 6, 2)->default(0);
            $table->decimal('leave_amount', 14, 2)->default(0);
            $table->decimal('other_dues', 14, 2)->default(0);
            $table->decimal('deductions', 14, 2)->default(0);
            $table->decimal('net', 14, 2)->default(0);
            $table->json('calculation')->nullable();
            $table->string('status', 20)->default('draft')->index(); // draft, paid
            $table->foreignId('payment_voucher_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('end_of_service_settlements');
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('employee_advances');
        Schema::dropIfExists('leave_requests');
        Schema::table('banks', fn (Blueprint $table) => $table->dropColumn('swift'));
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_id');
            $table->dropColumn(['job_title', 'department', 'employment_type', 'status', 'contract_end_date', 'termination_date', 'housing_allowance',
                'transport_allowance', 'other_allowances', 'gosi_subscribed', 'gosi_number', 'iban', 'annual_leave_days', 'weekly_hours']);
        });
    }
};
