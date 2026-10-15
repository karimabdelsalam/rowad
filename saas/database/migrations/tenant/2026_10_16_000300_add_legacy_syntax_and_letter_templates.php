<?php

use Database\Seeders\Tenant\TemplateSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Templates copied from the legacy system keep the legacy meaning of every placeholder (legacy_syntax).
 * Also adds the default templates of the letter types to offices created before they existed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('print_templates', function (Blueprint $table) {
            $table->boolean('legacy_syntax')->default(false)->after('key');
        });

        app(TemplateSeeder::class)->run(); // firstOrCreate: only missing templates are added
    }

    public function down(): void
    {
        Schema::table('print_templates', fn (Blueprint $table) => $table->dropColumn('legacy_syntax'));
    }
};
