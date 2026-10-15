<?php

namespace App\Filament\Platform\Resources\Plans\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('slug')
                    ->required(),
                TextInput::make('name_ar')
                    ->required(),
                TextInput::make('name_en')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('monthly_price')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('$'),
                TextInput::make('yearly_price')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->prefix('$'),
                TextInput::make('currency')
                    ->required()
                    ->default('SAR'),
                TextInput::make('max_units')
                    ->numeric(),
                TextInput::make('max_users')
                    ->numeric(),
                TextInput::make('max_storage_mb')
                    ->numeric(),
                TextInput::make('max_sms_per_month')
                    ->numeric(),
                CheckboxList::make('features')
                    ->options([
                        'owner_portal' => __('بوابة الملاك'),
                        'client_portal' => __('بوابة العملاء'),
                        'landing_page' => __('موقع المكتب'),
                        'zatca' => __('الفوترة الإلكترونية ZATCA'),
                        'campaigns' => __('الرسائل الجماعية'),
                        'api' => __('واجهة برمجية API'),
                        'custom_domain' => __('نطاق خاص'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                TextInput::make('trial_days')
                    ->required()
                    ->numeric()
                    ->default(14),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('sort_order')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
