<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * إعدادات الكونسول — جدول مفتاح/قيمة يُقرأ مرة واحدة لكل طلب.
 *
 * الكاش داخل النسخة لا في الكاش العام: الإعدادات تتغير من الواجهة ويجب أن يسري
 * التغيير فورًا دون انتظار انتهاء صلاحية كاش.
 */
class SettingsService
{
    private ?array $cache = null;

    public function get(string $key, string $default = ''): string
    {
        $this->cache ??= DB::table('settings')->pluck('svalue', 'skey')->all();

        return ($this->cache[$key] ?? '') !== '' ? (string)$this->cache[$key] : $default;
    }

    public function set(string $key, string $value): void
    {
        DB::table('settings')->updateOrInsert(['skey' => $key], ['svalue' => $value]);
        $this->cache = null;
    }

    public function all(): array
    {
        $this->cache ??= DB::table('settings')->pluck('svalue', 'skey')->all();

        return $this->cache;
    }
}
