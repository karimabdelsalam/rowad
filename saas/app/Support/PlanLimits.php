<?php

namespace App\Support;

use App\Enums\MessageChannel;
use App\Exceptions\BusinessRuleException;
use App\Models\AiMessage;
use App\Models\OutgoingMessage;
use App\Models\Plan;
use App\Models\Unit;
use App\Models\User;

/**
 * The organisation's plan: feature switches and quotas (legacy updatecore plan checks).
 * No plan means unrestricted (offices created before plans, or by the platform for itself).
 */
class PlanLimits
{
    /** Plan features users can see; ZATCA QR on invoices is a legal requirement and is never switched off. */
    public const FEATURES = [
        'owner_portal' => 'بوابة الملاك',
        'client_portal' => 'بوابة المستأجرين والمشترين',
        'campaigns' => 'الرسائل الجماعية',
        'api' => 'تطبيق الجوال وواجهة API',
        'landing_page' => 'موقع المؤسسة العام',
        'zatca' => 'الفوترة الإلكترونية (زاتكا)',
        'custom_domain' => 'نطاق خاص باسم مؤسستك',
        'ai' => 'المساعد الذكي والتحليلات',
    ];

    /** quota key => [plan column, label] */
    public const QUOTAS = [
        'units' => ['max_units', 'الوحدات'],
        'users' => ['max_users', 'المستخدمون'],
        'storage_mb' => ['max_storage_mb', 'التخزين (ميجا)'],
        'sms' => ['max_sms_per_month', 'رسائل SMS هذا الشهر'],
        'ai' => ['max_ai_messages_per_month', 'أسئلة المساعد الذكي هذا الشهر'],
    ];

    public static function plan(): ?Plan
    {
        return tenancy()->initialized ? tenant()->plan : null;
    }

    public static function allows(string $feature): bool
    {
        $plan = self::plan();

        return $plan === null || $plan->hasFeature($feature);
    }

    /** null = unlimited */
    public static function limit(string $quota): ?int
    {
        $value = self::plan()?->{self::QUOTAS[$quota][0]};

        return $value === null ? null : (int) $value;
    }

    public static function used(string $quota): float
    {
        return match ($quota) {
            'units' => Unit::count(),
            'users' => User::where('type', 'staff')->count(),
            'storage_mb' => (float) (tenant('usage')['storage_mb'] ?? 0), // measured hourly by rowad:usage
            'sms' => OutgoingMessage::where('channel', MessageChannel::Sms)->where('status', 'sent')
                ->where('sent_at', '>=', now()->startOfMonth())->count(),
            'ai' => AiMessage::where('role', 'assistant')->where('created_at', '>=', now()->startOfMonth())->count(),
        };
    }

    public static function reached(string $quota): bool
    {
        $limit = self::limit($quota);

        return $limit !== null && self::used($quota) >= $limit;
    }

    /** Stop an action that would go over the plan, with a message that says what to do. */
    public static function ensureRoomFor(string $quota): void
    {
        if (self::reached($quota)) {
            throw new BusinessRuleException(__('وصلت إلى الحد المسموح في باقتك (:quota: :limit). لإضافة المزيد اطلب ترقية الباقة من صفحة الاشتراك.', [
                'quota' => __(self::QUOTAS[$quota][1]), 'limit' => self::limit($quota),
            ]));
        }
    }
}
