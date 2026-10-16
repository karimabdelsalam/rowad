<?php

namespace App\Filament\Admin\Resources\Campaigns\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CampaignsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subject')->label(__('الحملة'))->weight('bold')->searchable()
                    ->state(fn ($record) => $record->subject ?: str($record->body)->limit(50))
                    ->description(fn ($record) => __('إلى :a', ['a' => \App\Filament\Admin\Resources\Campaigns\Schemas\CampaignInfolist::audience($record->audience)])),
                TextColumn::make('channel')->label(__('القناة'))->badge(),
                TextColumn::make('status')->label(__('الحالة'))->badge()
                    ->formatStateUsing(fn ($state) => \App\Filament\Admin\Resources\Campaigns\Schemas\CampaignInfolist::status($state))
                    ->color(fn ($state) => \App\Filament\Admin\Resources\Campaigns\Schemas\CampaignInfolist::STATUSES[$state][1] ?? 'gray'),
                TextColumn::make('recipients_count')->label(__('المستلمون'))->numeric()->alignCenter()->sortable(),
                TextColumn::make('sent_count')->label(__('أُرسلت'))->numeric()->alignCenter()->color('success')->sortable(),
                TextColumn::make('failed_count')->label(__('فشلت'))->numeric()->alignCenter()->color(fn ($state) => $state > 0 ? 'danger' : 'gray')->sortable(),
                TextColumn::make('scheduled_at')->label(__('موعد الإرسال'))->dateTime('Y-m-d H:i')->placeholder(__('فوري'))->sortable()->toggleable(),
                TextColumn::make('creator.name')->label(__('أنشأها'))->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label(__('التاريخ'))->date('Y-m-d')->sortable(),
            ])
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('channel')->label(__('القناة'))->options(\App\Enums\MessageChannel::class),
                \Filament\Tables\Filters\SelectFilter::make('status')->label(__('الحالة'))
                    ->options(collect(\App\Filament\Admin\Resources\Campaigns\Schemas\CampaignInfolist::STATUSES)->map(fn ($v) => __($v[0]))->all()),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('send')
                    ->label(__('إرسال الآن'))
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedPaperAirplane)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(fn (\App\Models\Campaign $record) => __('سيتم الإرسال إلى :n مستلم', [
                        'n' => app(\App\Services\Messaging\CampaignService::class)->recipients($record->audience)
                            ->pluck($record->channel->value === 'mail' ? 'email' : 'mobile')->filter()->unique()->count(),
                    ]))
                    ->visible(fn (\App\Models\Campaign $record) => $record->status === 'draft'
                        && (auth()->user()?->is_super_admin || auth()->user()?->can('campaigns.send')))
                    ->action(function (\App\Models\Campaign $record) {
                        try {
                            $n = app(\App\Services\Messaging\CampaignService::class)->send($record);
                            \Filament\Notifications\Notification::make()->title(__('تمت جدولة :n رسالة', ['n' => $n]))->success()->send();
                        } catch (\App\Exceptions\BusinessRuleException $e) {
                            \Filament\Notifications\Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
                ViewAction::make(),
                EditAction::make()->visible(fn (\App\Models\Campaign $record) => $record->status === 'draft'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
