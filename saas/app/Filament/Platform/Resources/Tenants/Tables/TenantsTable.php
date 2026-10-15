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
                TextColumn::make('email')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->dateTime('Y-m-d')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(TenantForm::statuses()),
                SelectFilter::make('plan_id')->relationship('plan', 'name_ar'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('recordPayment')
                    ->label(__('تسجيل دفعة اشتراك'))
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->modalHeading(__('تسجيل دفعة اشتراك'))
                    ->modalDescription(__('تُفعَّل المؤسسة على الباقة المدفوعة وتمتد الفترة من نهاية الفترة الحالية.'))
                    ->fillForm(fn (Tenant $record) => [
                        'plan_id' => $record->upgrade_request['plan_id'] ?? $record->plan_id,
                        'billing_cycle' => 'monthly',
                        'amount' => ($plan = Plan::find($record->upgrade_request['plan_id'] ?? $record->plan_id)) ? SubscriptionManager::price($plan, 'monthly') : null,
                        'starts_at' => SubscriptionManager::nextStart($record)->toDateString(),
                    ])
                    ->schema([
                        Select::make('plan_id')->label(__('الباقة'))->options(fn () => Plan::orderBy('sort_order')->pluck('name_ar', 'id'))->required()->live()
                            ->afterStateUpdated(fn (Get $get, Set $set, $state) => $set('amount', ($plan = Plan::find($state)) ? SubscriptionManager::price($plan, $get('billing_cycle')) : null)),
                        Select::make('billing_cycle')->label(__('دورة الفوترة'))->options(fn () => array_map('__', SubscriptionManager::CYCLES))->required()->live()
                            ->afterStateUpdated(fn (Get $get, Set $set, $state) => $set('amount', ($plan = Plan::find($get('plan_id'))) ? SubscriptionManager::price($plan, $state) : null)),
                        TextInput::make('amount')->label(__('المبلغ المدفوع شامل الضريبة'))->numeric()->minValue(0)->required()->suffix(__('ر.س')),
                        DatePicker::make('starts_at')->label(__('بداية الفترة'))->required(),
                        TextInput::make('reference')->label(__('مرجع الدفع'))->helperText(__('رقم التحويل البنكي أو الإيصال.'))->maxLength(190),
                    ])
                    ->action(function (Tenant $record, array $data) {
                        $subscription = app(SubscriptionManager::class)->record($record, Plan::findOrFail($data['plan_id']), $data['billing_cycle'],
                            (float) $data['amount'], Carbon::parse($data['starts_at']), $data['reference'] ?? null);
                        Notification::make()->success()
                            ->title(__('تم تفعيل الاشتراك حتى :date', ['date' => $subscription->ends_at->format('Y-m-d')]))->send();
                    }),
                ActionGroup::make([
                    Action::make('open')
                        ->label(__('فتح لوحة المؤسسة'))
                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->url(fn (Tenant $record) => request()->getScheme().'://'.$record->domains->first()?->domain.'/admin', shouldOpenInNewTab: true),
                    Action::make('upgradeHandled')
                        ->label(__('تمت معالجة طلب الترقية'))
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->visible(fn (Tenant $record) => filled($record->upgrade_request))
                        ->requiresConfirmation()
                        ->modalDescription(__('غيّر الباقة من تعديل المؤسسة أولًا، ثم أزل الطلب من القائمة.'))
                        ->action(fn (Tenant $record) => $record->forceFill(['upgrade_request' => null])->save()),
                    Action::make('suspend')
                        ->label(__('إيقاف'))
                        ->color('danger')
                        ->icon(Heroicon::OutlinedPauseCircle)
                        ->requiresConfirmation()
                        ->visible(fn (Tenant $record) => $record->status !== Tenant::STATUS_SUSPENDED)
                        ->action(fn (Tenant $record) => app(OfficeProvisioner::class)->suspend($record)),
                    Action::make('activate')
                        ->label(__('تفعيل'))
                        ->color('success')
                        ->icon(Heroicon::OutlinedPlayCircle)
                        ->requiresConfirmation()
                        ->visible(fn (Tenant $record) => $record->status !== Tenant::STATUS_ACTIVE)
                        ->action(fn (Tenant $record) => app(OfficeProvisioner::class)->activate($record)),
                ]),
            ]);
    }
}
