<?php

namespace App\Filament\Admin\Resources\InboxMessages\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InboxMessagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('source')
                    ->searchable(),
                TextColumn::make('unit.title')
                    ->searchable(),
                TextColumn::make('locale')
                    ->searchable(),
                TextColumn::make('ip_address')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('mobile')
                    ->searchable(),
                TextColumn::make('subject')
                    ->searchable(),
                TextColumn::make('read_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('replied_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                \Filament\Tables\Filters\TernaryFilter::make('read_at')->label(__('مقروءة'))->nullable(),
                \Filament\Tables\Filters\TernaryFilter::make('replied_at')->label(__('تم الرد'))->nullable(),
                \Filament\Tables\Filters\SelectFilter::make('source')->label(__('المصدر'))->options(['contact' => __('نموذج التواصل'), 'unit_inquiry' => __('استفسار عن وحدة')]),
            ])
            ->recordActions([
                ViewAction::make(),
                \App\Filament\Admin\Resources\InboxMessages\InboxActions::reply(),
                \App\Filament\Admin\Resources\InboxMessages\InboxActions::toggleRead(),
            ])
            ->defaultSort('id', 'desc')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
