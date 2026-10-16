<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Multi-branch offices: each building, unit, voucher and staff member may belong to one branch. */
return new class extends Migration
{
    private const TABLES = ['users', 'buildings', 'units', 'receipt_vouchers', 'payment_vouchers'];

    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('id')->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('branch_id');
            });
        }
        Schema::dropIfExists('branches');
    }
};
