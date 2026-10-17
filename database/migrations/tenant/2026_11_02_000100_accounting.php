<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Double-entry accounting: a chart of accounts, and journal entries with balanced lines. Vouchers and the
 * owner ledger post their entries automatically; the accountant adds manual ones (opening balances,
 * accruals, corrections).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('type', 20)->index();            // asset, liability, equity, revenue, expense
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->boolean('is_group')->default(false);    // a heading: holds sub-accounts, takes no lines itself
            $table->string('system_key', 40)->nullable()->unique(); // accounts the automatic postings use (cash, owners…)
            $table->foreignId('bank_account_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->date('entry_date')->index();
            $table->string('description');
            $table->nullableMorphs('source');               // the voucher / ledger entry it was posted from; null = manual
            $table->decimal('total', 14, 2)->default(0);
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->string('memo')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained()->nullOnDelete(); // owners' trust lines, per owner
            $table->index(['account_id', 'journal_entry_id']);
        });

        // the account a voucher that isn't tied to rent, owners or payroll goes to (default: other income / general expenses)
        // (position after each table's own type column — SQLite ignores after(), MySQL needs it to exist)
        Schema::table('receipt_vouchers', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('transaction_type_id')->constrained()->nullOnDelete();
        });
        Schema::table('payment_vouchers', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('expense_type_id')->constrained()->nullOnDelete();
        });

        $now = now();
        $ids = [];
        foreach (self::chart() as [$code, $name, $nameEn, $type, $parent, $key, $group]) {
            $ids[$code] = DB::table('accounts')->insertGetId([
                'code' => $code, 'name' => $name, 'name_en' => $nameEn, 'type' => $type, 'parent_id' => $parent ? $ids[$parent] : null,
                'is_group' => $group, 'system_key' => $key, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        (new \Database\Seeders\Tenant\RolesAndPermissionsSeeder)->run();

        // an office upgrading with history: post what its vouchers and owner ledger already hold
        app(\App\Services\Accounting\Journal::class)->rebuild();
    }

    /** A real-estate office's starting chart. @return list<array{string, string, string, string, ?string, ?string, bool}> */
    public static function chart(): array
    {
        return [
            ['1', 'الأصول', 'Assets', 'asset', null, null, true],
            ['11', 'النقدية والبنوك', 'Cash and banks', 'asset', '1', 'cash_and_banks', true],
            ['1101', 'الصندوق', 'Cash on hand', 'asset', '11', 'cash', false],
            ['12', 'الذمم المدينة', 'Receivables', 'asset', '1', null, true],
            ['1201', 'ذمم المستأجرين والمشترين', 'Tenants and buyers', 'asset', '12', 'tenants', false],
            ['1202', 'سلف الموظفين', 'Employee advances', 'asset', '12', 'employee_advances', false],
            ['13', 'ضريبة القيمة المضافة على المشتريات', 'Input VAT', 'asset', '1', 'vat_input', false],
            ['14', 'الأصول الثابتة', 'Fixed assets', 'asset', '1', null, true],
            ['1401', 'الأثاث والأجهزة', 'Furniture and equipment', 'asset', '14', null, false],
            ['2', 'الخصوم', 'Liabilities', 'liability', null, null, true],
            ['21', 'مستحقات الملاك (أمانات)', 'Owners\' funds held in trust', 'liability', '2', 'owners', false],
            ['22', 'ضريبة القيمة المضافة المستحقة', 'Output VAT', 'liability', '2', 'vat_output', false],
            ['23', 'ضرائب مستحقة أخرى', 'Other taxes payable', 'liability', '2', 'taxes_payable', false],
            ['24', 'رواتب مستحقة', 'Salaries payable', 'liability', '2', 'salaries_payable', false],
            ['25', 'التأمينات الاجتماعية المستحقة', 'GOSI payable', 'liability', '2', 'gosi_payable', false],
            ['3', 'حقوق الملكية', 'Equity', 'equity', null, null, true],
            ['31', 'رأس المال', 'Capital', 'equity', '3', 'capital', false],
            ['32', 'الأرباح المبقاة', 'Retained earnings', 'equity', '3', 'retained_earnings', false],
            ['33', 'المسحوبات الشخصية', 'Drawings', 'equity', '3', null, false],
            ['4', 'الإيرادات', 'Revenue', 'revenue', null, null, true],
            ['41', 'عمولات التأجير', 'Rental commissions', 'revenue', '4', 'rent_commission', false],
            ['42', 'عمولات البيع', 'Sale commissions', 'revenue', '4', 'sale_commission', false],
            ['43', 'أتعاب إدارة الأملاك', 'Property management fees', 'revenue', '4', 'management_fees', false],
            ['44', 'إيرادات أخرى', 'Other income', 'revenue', '4', 'other_income', false],
            ['5', 'المصروفات', 'Expenses', 'expense', null, null, true],
            ['51', 'الرواتب والأجور', 'Salaries and wages', 'expense', '5', 'salaries', false],
            ['52', 'الإيجار والمرافق', 'Rent and utilities', 'expense', '5', null, false],
            ['53', 'مصروفات تشغيلية عامة', 'General operating expenses', 'expense', '5', 'general_expenses', false],
            ['54', 'التسويق والإعلان', 'Marketing and advertising', 'expense', '5', null, false],
            ['55', 'رسوم بنكية وبوابات دفع', 'Bank and payment gateway fees', 'expense', '5', null, false],
            ['56', 'ضريبة الدخل والزكاة', 'Income tax and zakat', 'expense', '5', 'income_tax', false],
        ];
    }

    public function down(): void
    {
        foreach (['receipt_vouchers', 'payment_vouchers'] as $vouchers) {
            Schema::table($vouchers, fn (Blueprint $table) => $table->dropConstrainedForeignId('account_id'));
        }
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounts');
    }
};
