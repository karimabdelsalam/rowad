<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جداول كونسول الاشتراكات — مطابقة حرفيًا لسكيما النظام الحالي.
 *
 * التطابق مقصود: الترحيل إلى لارافيل يستبدل طبقة التطبيق فقط ولا يمسّ البيانات،
 * فتظل النسخة القديمة قادرة على العمل على نفس القاعدة طوال فترة الترحيل — وهو
 * ما يجعل التراجع ممكنًا في أي لحظة بدل أن يكون الترحيل قفزة بلا رجعة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('console_users', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name', 100);
            $t->string('username', 50)->unique();
            $t->string('password', 255);
            $t->string('totp_secret', 32)->nullable();
            $t->boolean('active')->default(true);
            $t->dateTime('created_at')->useCurrent();
        });

        Schema::create('plans', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name', 100);
            $t->integer('months')->default(1);
            $t->decimal('price', 10, 2)->default(0);
            $t->text('features')->nullable();
            $t->boolean('active')->default(true);
        });

        Schema::create('clinics', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name', 150);
            $t->string('owner_name', 150)->default('');
            $t->string('phone', 30)->default('');
            $t->string('email', 150)->default('');
            $t->string('site_url', 255)->default('');
            $t->string('subdomain', 40)->nullable()->unique('uq_subdomain');
            $t->string('custom_domain', 120)->default('');
            $t->string('db_name', 64)->default('');
            $t->unsignedInteger('plan_id')->nullable();
            $t->enum('status', ['trial', 'active', 'suspended', 'cancelled'])->default('trial');
            $t->date('start_date')->nullable();
            $t->date('expires_at')->nullable();
            $t->string('token', 64)->unique('uq_token');
            $t->dateTime('last_ping_at')->nullable();
            $t->string('app_version', 20)->default('');
            $t->integer('patients_count')->default(0);
            $t->integer('users_count')->default(0);
            $t->text('notes')->nullable();
            $t->dateTime('created_at')->useCurrent();

            $t->index(['status', 'expires_at'], 'idx_status');
            $t->index('custom_domain', 'idx_custom_domain');
        });

        Schema::create('invoices', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('clinic_id');
            $t->string('number', 30)->unique('uq_number');
            $t->date('issue_date');
            $t->date('due_date')->nullable();
            $t->decimal('amount', 10, 2)->default(0);
            $t->decimal('paid', 10, 2)->default(0);
            $t->integer('months')->default(1);
            $t->string('plan_name', 100)->default('');
            $t->enum('status', ['unpaid', 'partial', 'paid', 'void'])->default('unpaid');
            // يمنع تكرار مدّ الاشتراك لنفس الفاتورة مهما أُعيد حساب حالتها
            $t->boolean('applied')->default(false);
            $t->string('pay_token', 64)->unique('uq_paytoken');
            $t->string('notes', 255)->default('');
            $t->unsignedInteger('created_by')->nullable();
            $t->dateTime('created_at')->useCurrent();

            $t->foreign('clinic_id')->references('id')->on('clinics')->cascadeOnDelete();
            $t->index(['status', 'due_date'], 'idx_status');
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('invoice_id')->nullable();
            $t->unsignedInteger('clinic_id');
            $t->date('pdate');
            $t->decimal('amount', 10, 2);
            $t->enum('method', ['cash', 'instapay', 'paymob'])->default('cash');
            $t->enum('status', ['pending', 'confirmed', 'failed'])->default('confirmed');
            $t->string('reference', 120)->default('');
            $t->string('gateway_order_id', 60)->default('');
            $t->string('gateway_txn_id', 60)->default('');
            $t->string('notes', 255)->default('');
            $t->unsignedInteger('created_by')->nullable();
            $t->dateTime('created_at')->useCurrent();

            $t->foreign('invoice_id')->references('id')->on('invoices')->cascadeOnDelete();
            $t->foreign('clinic_id')->references('id')->on('clinics')->cascadeOnDelete();
            $t->index('pdate', 'idx_date');
            $t->index('gateway_order_id', 'idx_gw');
        });

        Schema::create('console_log', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('user_id')->nullable();
            $t->string('action', 30);
            $t->string('entity', 30);
            $t->unsignedInteger('entity_id')->nullable();
            $t->string('summary', 255)->default('');
            $t->string('ip', 45)->default('');
            $t->dateTime('created_at')->useCurrent();

            $t->index('created_at', 'idx_created');
        });

        Schema::create('settings', function (Blueprint $t) {
            $t->string('skey', 50)->primary();
            $t->text('svalue');
        });
    }

    public function down(): void
    {
        // الترتيب معكوس لاحترام المفاتيح الأجنبية
        Schema::dropIfExists('settings');
        Schema::dropIfExists('console_log');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('clinics');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('console_users');
    }
};
