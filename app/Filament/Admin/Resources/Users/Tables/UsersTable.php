<?php

namespace App\Filament\Admin\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('الاسم'))->weight('bold')->searchable()->sortable()
                    ->description(fn ($record) => $record->email ?? $record->username),
                TextColumn::make('type')->label(__('النوع'))->badge(),
                TextColumn::make('roles.name')->label(__('الأدوار'))->badge()->color('info')->placeholder('-')
                    ->state(fn ($record) => $record->is_super_admin ? [__('مدير النظام')] : $record->getRoleNames()->all()),
                TextColumn::make('mobile')->label(__('الجوال'))->searchable()->placeholder('-')->extraAttributes(['dir' => 'ltr'])->toggleable(),
                IconColumn::make('is_active')->label(__('مفعل'))->boolean()->alignCenter(),
                IconColumn::make('sms_login_enabled')->label('SMS')->boolean()->alignCenter()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_login_at')->label(__('آخر دخول'))->since()->sortable()->placeholder(__('لم يدخل بعد')),
                TextColumn::make('created_at')->label(__('أُضيف في'))->date('Y-m-d')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('type')->label(__('النوع'))->options(\App\Enums\UserType::class),
                \Filament\Tables\Filters\TernaryFilter::make('is_active')->label(__('مفعل')),
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
