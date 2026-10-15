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
                TextColumn::make('channel')
                    ->badge()
                    ->searchable(),
                TextColumn::make('audience')
                    ->searchable(),
                TextColumn::make('subject')
                    ->searchable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('recipients_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('sent_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('failed_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('scheduled_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_by')
                    ->numeric()
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
                //
            ])
            ->recordActions([
                \Filament\Actions\Action::make('send')
                    ->label(__('إرسال الآن'))
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedPaperAirplane)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(fn (\App\Models\Campaign $record) => __('سيتم الإرسال إلى :n مستلم', [
                        'n' => app(\App\Services\Messaging\CampaignService::class)->recipients($record->audience)
                            ->pluck($record->channel->value === 'sms' ? 'mobile' : 'email')->filter()->unique()->count(),
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
