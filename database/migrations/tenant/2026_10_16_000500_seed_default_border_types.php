<?php

use App\Models\Lookups\BorderType;
use Illuminate\Database\Migrations\Migration;

/** Offices created before border codes existed get the legacy default boundary types. */
return new class extends Migration
{
    public function up(): void
    {
        if (BorderType::whereNotNull('code')->exists()) {
            return;
        }
        foreach ([['street', 'LW', 'شارع'], ['blocked', 'LW', 'شارع سد'], ['path', 'LW', 'ممر'], ['neighbour', 'L', 'جار'], ['facility', 'L', 'مرفق'],
            ['build', 'NL', 'قطعة'], ['lobby', 'L', 'بهو'], ['farm', 'L', 'مزرعة'], ['rest', 'L', 'استراحة'], ['gbuilding', 'NL', 'مبنى حكومي']] as [$code, $attrs, $title]) {
            BorderType::firstOrCreate(['code' => $code], ['title' => $title, 'attributes' => str_split($attrs)]);
        }
    }

    public function down(): void
    {
    }
};
