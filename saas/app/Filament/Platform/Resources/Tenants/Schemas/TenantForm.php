<?php

namespace App\Filament\Platform\Resources\Tenants\Schemas;

use App\Models\Tenant;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TenantForm
{
    public static function statuses(): array
    {
        return [
            Tenant::STATUS_TRIAL => __('تجريبي'),
            Tenant::STATUS_ACTIVE => __('نشط'),
            Tenant::STATUS_PAST_DUE => __('متأخر السداد'),
            Tenant::STATUS_SUSPENDED => __('موقوف'),
            Tenant::STATUS_CANCELLED => __('ملغي'),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('بيانات المكتب'))->columns(2)->schema([
                TextInput::make('name')->label(__('اسم المكتب'))->required()->maxLength(150),
                TextInput::make('email')->email(),
                TextInput::make('phone')->tel(),
                Select::make('plan_id')->relationship('plan', 'name_ar')->preload(),
                TextInput::make('subdomain')
                    ->label(__('النطاق الفرعي'))
                    ->required()
                    ->alphaDash()
                    ->minLength(3)
                    ->maxLength(40)
                    ->suffix('.'.config('rowad.tenancy.base_domain'))
                    ->visibleOn('create'),
                Select::make('status')->options(self::statuses())->required()->visibleOn('edit'),
                DateTimePicker::make('trial_ends_at')->visibleOn('edit'),
            ]),
            Section::make(__('مدير المكتب'))->columns(2)->visibleOn('create')->schema([
                TextInput::make('admin_name')->label(__('الاسم'))->required(),
                TextInput::make('admin_email')->label(__('البريد الإلكتروني'))->email()->required(),
                TextInput::make('admin_password')->label(__('كلمة المرور'))->password()->revealable()->required()->minLength(8),
                Select::make('locale')->label(__('لغة النظام'))->options(['ar' => 'العربية', 'en' => 'English'])->default('ar'),
            ]),
        ]);
    }
}
