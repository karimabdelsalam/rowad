<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** The AI assistant is in every plan, with a monthly number of questions per plan (it costs per use). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('max_ai_messages_per_month')->nullable()->after('max_sms_per_month');
        });

        foreach (['basic' => 100, 'professional' => 600, 'enterprise' => 3000] as $slug => $quota) {
            $plan = DB::table('plans')->where('slug', $slug)->first();
            if ($plan === null) {
                continue;
            }
            $features = json_decode((string) $plan->features, true) ?: [];
            DB::table('plans')->where('id', $plan->id)->update([
                'max_ai_messages_per_month' => $quota,
                'features' => json_encode(array_values(array_unique([...$features, 'ai']))),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('max_ai_messages_per_month');
        });
    }
};
