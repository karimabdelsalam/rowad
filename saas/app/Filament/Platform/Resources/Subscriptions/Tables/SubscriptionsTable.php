<?php

namespace App\Filament\Platform\Resources\Subscriptions\Tables;

use App\Services\Platform\SubscriptionManager;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SubscriptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['tenant', 'plan']))
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('tenant.name')->label(__('المؤسسة'))->searchable()->weight('bold'),
                TextColumn::make('plan.name_ar')->label(__('الباقة'))->badge()->color('gray'),
                TextColumn::make('billing_cycle')->label(__('الدورة'))->formatStateUsing(fn (string $state) => __(SubscriptionManager::CYCLES[$state] ?? $state)),
                TextColumn::make('status')->label(__('الحالة'))->badge()
                    ->formatStateUsing(fn (string $state) => __(SubscriptionManager::STATUSES[$state] ?? $state))
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'success',
                        'trialing' => 'info',
                        'past_due' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('amount')->label(__('المبلغ'))->numeric(2)->suffix(fn ($record) => ' '.($record->currency === 'SAR' ? __('ر.س') : $record->currency))->sortable(),
                TextColumn::make('starts_at')->label(__('من'))->date('Y-m-d')->sortable(),
                TextColumn::make('ends_at')->label(__('إلى'))->date('Y-m-d')->sortable()->placeholder('-'),
                TextColumn::make('gateway_reference')->label(__('مرجع الدفع'))->placeholder('-')->toggleable()->searchable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('الحالة'))->options(fn () => array_map('__', SubscriptionManager::STATUSES)),
                SelectFilter::make('plan_id')->label(__('الباقة'))->relationship('plan', 'name_ar'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
