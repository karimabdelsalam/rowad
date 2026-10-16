<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Pages\GeneralSettings;
use App\Filament\Admin\Pages\WebsiteSettings;
use App\Filament\Admin\Resources\Owners\OwnerResource;
use App\Filament\Admin\Resources\RentContracts\RentContractResource;
use App\Filament\Admin\Resources\Units\UnitResource;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\Owner;
use App\Models\RentContract;
use App\Models\Unit;
use App\Models\User;
use App\Support\Settings;
use App\Support\Website;
use Filament\Widgets\Widget;

/** Checklist for a new office: what to set up first, with live done-state; disappears once done or dismissed. */
class GettingStarted extends Widget
{
    protected static ?int $sort = 1; // right under the welcome banner (sort 0)

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.getting-started';

    public static function canView(): bool
    {
        return GeneralSettings::canAccess() && ! Settings::enabled('onboarding_dismissed') && static::remaining() > 0;
    }

    public static function remaining(): int
    {
        return count(array_filter(static::steps(), fn ($s) => ! $s['done']));
    }

    /** @return list<array{key: string, label: string, hint: string, done: bool, url: ?string}> */
    public static function steps(): array
    {
        $staff = User::where('type', 'staff')->count();

        return [
            ['key' => 'identity', 'label' => __('أكمل بيانات المؤسسة وشعارها'), 'hint' => __('الاسم والشعار والهاتف تظهر في البوابات والمطبوعات'),
                'done' => filled(Settings::get('logo')) && filled(Settings::get('phone') ?: Settings::get('mobile')), 'url' => GeneralSettings::getUrl()],
            ['key' => 'owner', 'label' => __('أضف أول مالك'), 'hint' => __('كل عقار يُسجّل باسم مالكه وحصصه'),
                'done' => Owner::count() > 0, 'url' => OwnerResource::canCreate() ? OwnerResource::getUrl('create') : null],
            ['key' => 'unit', 'label' => __('أضف أول عقار ووحدة'), 'hint' => __('المبنى ثم وحداته، أو وحدة مستقلة'),
                'done' => Unit::count() > 0, 'url' => UnitResource::canCreate() ? UnitResource::getUrl('create') : null],
            ['key' => 'contract', 'label' => __('سجّل أول عقد إيجار'), 'hint' => __('يبني النظام جدول الدفعات تلقائيًا'),
                'done' => RentContract::count() > 0, 'url' => RentContractResource::canCreate() ? RentContractResource::getUrl('create') : null],
            ['key' => 'sms', 'label' => __('فعّل الرسائل النصية'), 'hint' => __('تذكير المستأجرين قبل الاستحقاق وبعد التأخر'),
                'done' => filled(Settings::get('sms_provider')), 'url' => GeneralSettings::getUrl()],
            ['key' => 'team', 'label' => __('أضف فريقك'), 'hint' => __('محاسب ومسوّق بصلاحيات محددة'),
                'done' => $staff > 1, 'url' => UserResource::canCreate() ? UserResource::getUrl('create') : null],
            ['key' => 'website', 'label' => __('فعّل موقع المؤسسة'), 'hint' => __('صفحة عامة لوحداتك المتاحة وطلبات المعاينة'),
                'done' => Website::enabled(), 'url' => WebsiteSettings::canAccess() ? WebsiteSettings::getUrl() : null],
        ];
    }

    public function dismiss(): void
    {
        abort_unless(GeneralSettings::canAccess(), 403);
        Settings::set('onboarding_dismissed', '1', 'general');
        Settings::flush();
        $this->redirect(request()->header('Referer') ?: \App\Filament\Admin\Pages\Dashboard::getUrl());
    }
}
