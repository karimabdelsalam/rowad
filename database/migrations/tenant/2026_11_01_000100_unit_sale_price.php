<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Asking price of a unit offered for sale, shown on the office's website (rent units use rent_amount). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', fn (Blueprint $table) => $table->decimal('sale_price', 14, 2)->nullable()->after('monthly_rent'));
    }

    public function down(): void
    {
        Schema::table('units', fn (Blueprint $table) => $table->dropColumn('sale_price'));
    }
};
