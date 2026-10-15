<?php

namespace App\Filament\Platform\Resources\Plans\Tables;

use App\Support\PlanLimits;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlansTable
{
    public static function configure(Table $table): Table
    {
        $limit = fn (string $name, string $label) => TextColumn::make($name)->label(__($label))->numeric()->placeholder(__('غير محدود'));

        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->modifyQueryUsing(fn ($query) => $query->withCount('tenants'))
            ->columns([
                TextColumn::make('name_ar')->label(__('الباقة'))->weight('bold')->description(fn ($record) => $record->name_en)->searchable(),
                TextColumn::make('monthly_price')->label(__('شهري'))->numeric()->suffix(' '.__('ر.س')),
                TextColumn::make('yearly_price')->label(__('سنوي'))->numeric()->suffix(' '.__('ر.س')),
                $limit('max_units', 'الوحدات'),
                $limit('max_users', 'المستخدمون'),
                TextColumn::make('features')->label(__('المزايا'))->badge()->color('gray')
                    ->formatStateUsing(fn (string $state) => __(PlanLimits::FEATURES[$state] ?? $state))->limitList(3)->expandableLimitedList(),
                TextColumn::make('tenants_count')->label(__('المؤسسات'))->numeric(),
                IconColumn::make('is_active')->label(__('متاحة'))->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
