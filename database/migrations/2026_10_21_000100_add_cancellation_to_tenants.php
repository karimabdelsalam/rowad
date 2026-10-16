<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Self-service cancellation: when the office cancelled and when its data may be purged (30-day retention). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('trial_ends_at');
            $table->timestamp('purge_after')->nullable()->index()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['cancelled_at', 'purge_after']);
        });
    }
};
