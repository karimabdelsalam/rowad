<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ejar: whether each rent contract is documented on the Ejar platform (and its number), plus the
 * date of birth Ejar verifies every lessor and lessee with (Yakeen/Absher).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rent_contracts', function (Blueprint $table) {
            $table->string('ejar_status', 20)->default('pending')->index()->after('status');
            $table->string('ejar_number', 40)->nullable()->after('ejar_status');
            $table->date('ejar_documented_at')->nullable()->after('ejar_number');
            $table->string('ejar_notes')->nullable()->after('ejar_documented_at');
        });
        foreach (['owners', 'clients', 'employees'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->date('birth_date')->nullable()->after('id_issue_date'));
        }
    }

    public function down(): void
    {
        foreach (['owners', 'clients', 'employees'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('birth_date'));
        }
        Schema::table('rent_contracts', function (Blueprint $table) {
            $table->dropIndex(['ejar_status']);
            $table->dropColumn(['ejar_status', 'ejar_number', 'ejar_documented_at', 'ejar_notes']);
        });
    }
};
