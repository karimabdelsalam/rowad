<?php

namespace App\Filament\Admin\Resources\Units\Schemas;

use App\Models\Unit;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class UnitInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('building.title')
                    ->label('Building')
                    ->placeholder('-'),
                TextEntry::make('title'),
                TextEntry::make('offer_type')
                    ->badge(),
                TextEntry::make('status')
                    ->badge(),
                IconEntry::make('is_active')
                    ->boolean(),
                TextEntry::make('property_category_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('clientCategory.title')
                    ->label('Client category')
                    ->placeholder('-'),
                TextEntry::make('number')
                    ->placeholder('-'),
                TextEntry::make('floor')
                    ->placeholder('-'),
                TextEntry::make('rooms')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('area')
                    ->numeric()
                    ->placeholder('-'),
                IconEntry::make('is_furnished')
                    ->boolean(),
                TextEntry::make('max_occupants')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('deedType.title')
                    ->label('Deed type')
                    ->placeholder('-'),
                TextEntry::make('deed_number')
                    ->placeholder('-'),
                TextEntry::make('plan_number')
                    ->placeholder('-'),
                TextEntry::make('plot_number')
                    ->placeholder('-'),
                TextEntry::make('zone')
                    ->placeholder('-'),
                TextEntry::make('street')
                    ->placeholder('-'),
                TextEntry::make('city.name')
                    ->label('City')
                    ->placeholder('-'),
                TextEntry::make('district.name')
                    ->label('District')
                    ->placeholder('-'),
                TextEntry::make('address')
                    ->placeholder('-'),
                TextEntry::make('address_details')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('latitude')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('longitude')
                    ->numeric()
                    ->placeholder('-'),
                \Filament\Infolists\Components\RepeatableEntry::make('deeds')->label(__('الصكوك'))->columnSpanFull()->columns(4)
                    ->schema([
                        TextEntry::make('deedType.title')->label(__('نوع الصك'))->placeholder('-'),
                        TextEntry::make('number')->label(__('رقم الصك')),
                        TextEntry::make('source')->label(__('مصدر الصك'))->placeholder('-'),
                        TextEntry::make('issue_date')->label(__('تاريخ الصك'))->placeholder('-'),
                    ])->placeholder('-'),
                \Filament\Infolists\Components\RepeatableEntry::make('landPlots')->label(__('القطع والحدود'))->columnSpanFull()->columns(3)
                    ->schema([
                        TextEntry::make('number')->label(__('رقم القطعة'))->placeholder('-'),
                        TextEntry::make('area')->label(__('المساحة (م²)'))->placeholder('-'),
                        TextEntry::make('boundaries')->label(__('الحدود'))->columnSpanFull()
                            ->state(fn (\App\Models\UnitPlot $record) => collect(\App\Models\UnitPlot::SIDES)
                                ->map(fn ($side) => filled($record->describeSide($side)) ? __(['north' => 'شمالًا', 'east' => 'شرقًا', 'south' => 'جنوبًا', 'west' => 'غربًا'][$side]).': '.$record->describeSide($side, true).(filled($record->sides[$side]['length'] ?? null) ? ' '.__('بطول').' '.$record->sides[$side]['length'].' '.__('م') : '') : null)
                                ->filter()->implode(' — ') ?: '-'),
                    ])->placeholder('-'),
                TextEntry::make('details')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('rent_amount')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('daily_rent')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('monthly_rent')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('rent_period')
                    ->badge()
                    ->placeholder('-'),
                TextEntry::make('rent_payment_cycle')
                    ->badge()
                    ->placeholder('-'),
                TextEntry::make('rent_commission')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('sale_commission')
                    ->numeric()
                    ->placeholder('-'),
                IconEntry::make('vat_applicable')
                    ->boolean(),
                TextEntry::make('management_fee')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('management_fee_cycle')
                    ->badge()
                    ->placeholder('-'),
                TextEntry::make('management_fee_start_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('management_fee_next_date')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('calendar')
                    ->badge(),
                TextEntry::make('current_contract_type')
                    ->placeholder('-'),
                TextEntry::make('current_contract_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Unit $record): bool => $record->trashed()),
            ]);
    }
}
