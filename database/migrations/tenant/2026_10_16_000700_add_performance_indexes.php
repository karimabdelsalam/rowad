<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Indexes for the filters the lists, reports and daily jobs use most (performance review). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installments', function (Blueprint $table) {
            $table->index(['is_collected', 'due_date']);   // pending / overdue lists, reminders, next payment
            $table->index('collected_at');                 // collections per month and year
        });
        Schema::table('rent_contracts', function (Blueprint $table) {
            $table->index('end_date');                     // expiring contracts, daily release of ended ones
            $table->index('next_payment_date');
        });
        Schema::table('owner_transactions', function (Blueprint $table) {
            $table->index(['owner_id', 'is_settled']);     // owner balances and statements
        });
    }

    public function down(): void
    {
        Schema::table('installments', function (Blueprint $table) {
            $table->dropIndex(['is_collected', 'due_date']);
            $table->dropIndex(['collected_at']);
        });
        Schema::table('rent_contracts', function (Blueprint $table) {
            $table->dropIndex(['end_date']);
            $table->dropIndex(['next_payment_date']);
        });
        Schema::table('owner_transactions', function (Blueprint $table) {
            $table->dropIndex(['owner_id', 'is_settled']);
        });
    }
};
