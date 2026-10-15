<?php

namespace App\Filament\Admin\Resources\Employees\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Filament\Tables\Columns\IconColumn;
use Filament\Support\Icons\Heroicon;

class EmployeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')->label(__('الاسم'))->weight('bold')->searchable(['full_name', 'first_name', 'family_name'])->sortable()
                    ->description(fn ($record) => $record->work),
                TextColumn::make('mobile')->label(__('الجوال'))->searchable()->icon(Heroicon::OutlinedPhone)->iconColor('gray')->extraAttributes(['dir' => 'ltr'])->placeholder('-'),
                TextColumn::make('email')->label(__('البريد الإلكتروني'))->searchable()->placeholder('-')->toggleable(isToggledHiddenByDefault: true)->extraAttributes(['dir' => 'ltr']),
                TextColumn::make('salary')->label(__('الراتب'))->numeric(2)->sortable()->toggleable(),
                TextColumn::make('start_date')->label(__('تاريخ التعيين'))->date('Y-m-d')->sortable()->toggleable(),
                TextColumn::make('id_expiry_date')->label(__('انتهاء الهوية'))->date('Y-m-d')->sortable()->placeholder('-')
                    ->color(fn ($record) => $record->id_expiry_date?->isPast() ? 'danger' : ($record->id_expiry_date && $record->id_expiry_date->diffInDays(today()) <= 60 ? 'warning' : null))
                    ->icon(fn ($record) => $record->id_expiry_date && $record->id_expiry_date->diffInDays(today(), false) >= -60 ? Heroicon::OutlinedExclamationTriangle : null),
                IconColumn::make('user.is_active')->label(__('الحساب'))->boolean()->alignCenter()->placeholder('-'),
                TextColumn::make('created_at')->label(__('أُضيف في'))->date('Y-m-d')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
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
