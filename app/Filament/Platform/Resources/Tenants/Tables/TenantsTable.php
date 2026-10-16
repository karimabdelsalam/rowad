<?php

namespace App\Filament\Platform\Resources\Tenants\Tables;

use App\Filament\Platform\Resources\Tenants\Schemas\TenantForm;
use App\Models\Tenant;
use App\Models\Plan;
use App\Services\Platform\OfficeProvisioner;
use App\Services\Platform\SubscriptionManager;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use App\Filament\Platform\Resources\Tenants\TenantActions;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TenantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['plan', 'domains'])
                ->withMax(['subscriptions as paid_until' => fn ($q) => $q->whereIn('status', ['active', 'past_due'])], 'ends_at'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label(__('اسم المؤسسة'))->searchable()->sortable()->weight('bold')
                    ->description(fn (Tenant $record) => $record->domains->pluck('domain')->implode(' · ')),
                TextColumn::make('plan.name_ar')->label(__('الباقة'))->badge(),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => TenantForm::statuses()[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        Tenant::STATUS_ACTIVE => 'success',
                        Tenant::STATUS_TRIAL => 'info',
                        Tenant::STATUS_PAST_DUE => 'warning',
                        default => 'danger',
                    }),
                // When the office has to pay next: the end of its trial, or of its paid period.
                TextColumn::make('renews_at')->label(__('ينتهي في'))->placeholder('-')
                    ->state(fn (Tenant $record) => $record->status === Tenant::STATUS_TRIAL ? $record->trial_ends_at : ($record->paid_until ? Carbon::parse($record->paid_until) : null))
                    ->date('Y-m-d')
                    ->color(fn ($state) => $state && Carbon::parse($state)->lt(now()->addDays(7)) ? 'danger' : null),
                TextColumn::make('upgrade_request.plan')->label(__('طلب ترقية'))->badge()->color('warning')
                    ->icon(Heroicon::OutlinedArrowTrendingUp)
                    ->tooltip(fn (Tenant $record) => $record->upgrade_request['note'] ?? null)
                    ->placeholder('-'),
                // AI questions this month against the plan's quota (measured hourly by rowad:usage)
                TextColumn::make('ai_usage')->label(__('الذكاء الاصطناعي'))->toggleable()
                    ->state(fn (Tenant $record) => isset($record->usage['ai_messages'])
                        ? number_format($record->usage['ai_messages']).' / '.($record->plan?->max_ai_messages_per_month ? number_format($record->plan->max_ai_messages_per_month) : '∞')
                        : null)
                    ->tooltip(fn (Tenant $record) => isset($record->usage['ai_input_tokens'])
                        ? __('رموز هذا الشهر: :in إدخال، :out إخراج', ['in' => number_format($record->usage['ai_input_tokens']), 'out' => number_format($record->usage['ai_output_tokens'] ?? 0)])
                        : null)
                    ->placeholder('-'),
                TextColumn::make('email')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->dateTime('Y-m-d')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(TenantForm::statuses()),
                SelectFilter::make('plan_id')->relationship('plan', 'name_ar'),
            ])
            ->recordUrl(fn (Tenant $record) => \App\Filament\Platform\Resources\Tenants\TenantResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ViewAction::make()->label(__('عرض')),
                TenantActions::recordPayment()->label(__('دفعة')),
                ActionGroup::make([
                    EditAction::make(),
                    TenantActions::open(),
                    TenantActions::loginAs(),
                    TenantActions::extendTrial(),
                    TenantActions::changeSubdomain(),
                    TenantActions::upgradeHandled(),
                    TenantActions::suspend(),
                    TenantActions::activate(),
                ]),
            ]);
    }
}
