<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class ConsoleUser extends Authenticatable
{
    public const UPDATED_AT = null;

    protected $table = 'console_users';

    protected $guarded = ['id'];

    protected $hidden = ['password', 'totp_secret'];

    protected $casts = ['active' => 'boolean', 'password' => 'hashed'];

    public function hasTwoFactor(): bool
    {
        return (string)$this->totp_secret !== '';
    }

    /*
     * لا عمود remember_token في السكيما، وهي مطابقة عمدًا للنظام القديم الذي لم
     * يدعم «تذكّرني» أصلًا. تعطيل الميزة هنا أسلم من إضافة عمود لأجلها: جلسة
     * دائمة على جهاز مشترك في كونسول يرى بيانات كل العملاء ليست ميزة مرغوبة.
     */
    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
    }

    public function getRememberTokenName(): ?string
    {
        return null;
    }
}
