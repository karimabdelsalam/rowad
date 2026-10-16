<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Collection escalation for late rent: where each instalment stands on the ladder and its warning letter. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installments', function (Blueprint $table) {
            $table->unsignedTinyInteger('escalation_stage')->default(0)->after('last_reminded_at'); // 0 none, 1 late notice, 2 second notice + task, 3 formal warning
            $table->timestamp('escalated_at')->nullable()->after('escalation_stage');
            $table->foreignId('warning_letter_id')->nullable()->after('escalated_at')->constrained('letters')->nullOnDelete();
        });

        (new \Database\Seeders\Tenant\TemplateSeeder)->run(); // the three late-rent messages
    }

    public function down(): void
    {
        Schema::table('installments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warning_letter_id');
            $table->dropColumn(['escalation_stage', 'escalated_at']);
        });
    }
};
