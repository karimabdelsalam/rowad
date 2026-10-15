<?php

namespace App\Filament\Admin\Resources\InboxMessages\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Columns\IconColumn;
use Filament\Support\Icons\Heroicon;

class InboxMessagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('read_at')->label('')->boolean()->trueIcon(Heroicon::OutlinedEnvelopeOpen)->falseIcon(Heroicon::Envelope)->trueColor('gray')->falseColor('warning')->alignCenter()
                    ->tooltip(fn ($record) => $record->read_at ? __('مقروءة') : __('جديدة')),
                TextColumn::make('name')->label(__('المرسل'))->weight(fn ($record) => $record->read_at ? null : 'bold')->searchable()
                    ->description(fn ($record) => $record->mobile ?: $record->email),
                TextColumn::make('subject')->label(__('الموضوع'))->searchable()->limit(50)
                    ->state(fn ($record) => $record->subject ?: str($record->message)->limit(50)),
                TextColumn::make('source')->label(__('المصدر'))->badge()->color('gray')
                    ->formatStateUsing(fn ($state) => $state === 'unit_inquiry' ? __('استفسار عن وحدة') : __('نموذج التواصل')),
                TextColumn::make('unit.title')->label(__('الوحدة'))->placeholder('-')->toggleable(),
                IconColumn::make('replied_at')->label(__('رُدّ عليها'))->boolean()->alignCenter(),
                TextColumn::make('created_at')->label(__('وصلت'))->since()->sortable()->tooltip(fn ($record) => $record->created_at?->format('Y-m-d H:i')),
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
