<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Legacy building.meters also held owner_type (private/shared), pay_code and pay_no per meter. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_meters', function (Blueprint $table) {
            $table->string('ownership', 10)->nullable()->after('number'); // private, shared
            $table->string('payment_code', 100)->nullable()->after('ownership');
            $table->string('payment_account', 100)->nullable()->after('payment_code');
        });
    }

    public function down(): void
    {
        Schema::table('unit_meters', function (Blueprint $table) {
            $table->dropColumn(['ownership', 'payment_code', 'payment_account']);
        });
    }
};
