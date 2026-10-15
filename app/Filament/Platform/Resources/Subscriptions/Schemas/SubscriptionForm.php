<?php

namespace App\Filament\Platform\Resources\Subscriptions\Schemas;

use App\Services\Platform\SubscriptionManager;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** Correcting a recorded period by hand; new payments go through "record payment" on the office. */
class SubscriptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('الاشتراك'))->columns(['default' => 1, 'md' => 2])->schema([
                    Select::make('tenant_id')->label(__('المؤسسة'))->relationship('tenant', 'name')->searchable()->preload()->required(),
                    Select::make('plan_id')->label(__('الباقة'))->relationship('plan', 'name_ar')->preload()->required(),
                    Select::make('billing_cycle')->label(__('دورة الفوترة'))->options(fn () => array_map('__', SubscriptionManager::CYCLES))->required()->default('monthly'),
                    Select::make('status')->label(__('الحالة'))->options(fn () => array_map('__', SubscriptionManager::STATUSES))->required()->default('active'),
                    TextInput::make('amount')->label(__('المبلغ'))->required()->numeric()->minValue(0)->suffix(__('ر.س')),
                    TextInput::make('currency')->label(__('العملة'))->required()->default('SAR')->length(3),
                ]),
                Section::make(__('الفترة'))->columns(['default' => 1, 'md' => 3])->schema([
                    DateTimePicker::make('starts_at')->label(__('تاريخ البدء'))->required(),
                    DateTimePicker::make('ends_at')->label(__('تاريخ الانتهاء'))->after('starts_at'),
                    DateTimePicker::make('cancelled_at')->label(__('تاريخ الإلغاء')),
                ]),
                Section::make(__('الدفع'))->columns(['default' => 1, 'md' => 2])->schema([
                    Select::make('gateway')->label(__('طريقة الدفع'))->options(['manual' => __('تحويل بنكي / يدوي'), 'moyasar' => 'Moyasar', 'tap' => 'Tap', 'paytabs' => 'PayTabs'])->default('manual'),
                    TextInput::make('gateway_reference')->label(__('مرجع الدفع')),
                ]),
            ]);
    }
}
