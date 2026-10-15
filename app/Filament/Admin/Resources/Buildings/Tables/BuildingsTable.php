<?php

namespace App\Filament\Admin\Resources\Buildings\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class BuildingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label(__('العقار'))->weight('bold')->searchable()->sortable()
                    ->description(fn ($record) => implode(' · ', array_filter([$record->city?->name, $record->district?->name, $record->street]))),
                TextColumn::make('category.title')->label(__('التصنيف'))->badge()->color('gray')->placeholder('-'),
                TextColumn::make('number')->label(__('الرقم'))->searchable()->placeholder('-')->toggleable(),
                TextColumn::make('units_count')->label(__('الوحدات'))->counts('units')->badge()->color('gray')->alignCenter()->sortable(),
                TextColumn::make('rented_count')->label(__('مؤجرة'))->badge()->color('success')->alignCenter()
                    ->state(fn ($record) => $record->units()->where('status', \App\Enums\UnitStatus::Rented)->count()),
                TextColumn::make('floors')->label(__('الأدوار'))->numeric()->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('area')->label(__('المساحة'))->numeric(2)->suffix(' م²')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('plan_number')->label(__('المخطط'))->searchable()->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('plot_number')->label(__('القطعة'))->searchable()->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label(__('أُضيف في'))->date('Y-m-d')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('property_category_id')->label(__('التصنيف'))->relationship('category', 'title'),
                \Filament\Tables\Filters\SelectFilter::make('city_id')->label(__('المدينة'))->relationship('city', 'name')->searchable()->preload(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make()->label(__('عرض')),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
