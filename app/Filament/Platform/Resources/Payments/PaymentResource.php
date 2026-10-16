<?php

namespace App\Filament\Platform\Resources\Payments;

use App\Filament\Platform\Nav;
use App\Filament\Platform\Resources\Tenants\TenantResource;
use App\Models\SubscriptionPayment;
use App\Services\Platform\SubscriptionManager;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Every riyal the platform received: one row per recorded subscription payment, with VAT split out. */
class PaymentResource extends Resource
{
    protected static ?string $model = SubscriptionPayment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = Nav::Offices;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'payments';

    protected static ?string $modelLabel = 'دفعة';

    protected static ?string $pluralModelLabel = 'المدفوعات';

    public const STATUSES = ['paid' => ['مدفوعة', 'success'], 'pending' => ['بانتظار التأكيد', 'warning'], 'failed' => ['فشلت', 'danger'], 'refunded' => ['مستردة', 'gray']];

    public static function canCreate(): bool
    {
        return false; // payments are recorded from the office page so the period is extended with them
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['subscription.tenant', 'subscription.plan']))
            ->defaultSort('paid_at', 'desc')
            ->striped()
            ->columns([
                TextColumn::make('paid_at')->label(__('التاريخ'))->dateTime('Y-m-d H:i')->sortable()->weight('bold'),
                TextColumn::make('subscription.tenant.name')->label(__('المؤسسة'))->weight('medium')->searchable()
                    ->url(fn (SubscriptionPayment $r) => $r->subscription?->tenant ? TenantResource::getUrl('view', ['record' => $r->subscription->tenant]) : null)->color('primary')
                    ->description(fn (SubscriptionPayment $r) => ($r->subscription?->plan?->name_ar ?? '-').' · '.__(SubscriptionManager::CYCLES[$r->subscription?->billing_cycle] ?? '')),
                TextColumn::make('amount')->label(__('المبلغ'))->numeric(2)->suffix(' '.__('ر.س'))->sortable()->weight('bold'),
                TextColumn::make('vat_amount')->label(__('منها ضريبة'))->numeric(2)->toggleable(),
                TextColumn::make('status')->label(__('الحالة'))->badge()
                    ->formatStateUsing(fn ($state) => __(self::STATUSES[$state][0] ?? $state))
                    ->color(fn ($state) => self::STATUSES[$state][1] ?? 'gray'),
                TextColumn::make('gateway')->label(__('الطريقة'))->badge()->color('gray')
                    ->formatStateUsing(fn ($state) => $state === 'manual' ? __('تحويل بنكي / يدوي') : ucfirst((string) $state)),
                TextColumn::make('gateway_reference')->label(__('المرجع'))->placeholder('-')->searchable()->fontFamily('mono')->size('xs')->copyable(),
                TextColumn::make('subscription.ends_at')->label(__('الفترة حتى'))->date('Y-m-d')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('الحالة'))->options(collect(self::STATUSES)->map(fn ($v) => __($v[0]))->all()),
                SelectFilter::make('plan')->label(__('الباقة'))->options(fn () => \App\Models\Plan::orderBy('sort_order')->pluck('name_ar', 'id'))
                    ->query(fn (Builder $q, array $data) => filled($data['value'] ?? null) ? $q->whereHas('subscription', fn (Builder $s) => $s->where('plan_id', $data['value'])) : $q),
                Filter::make('paid_at')->label(__('الفترة'))
                    ->schema([\Filament\Forms\Components\DatePicker::make('from')->label(__('من تاريخ')), \Filament\Forms\Components\DatePicker::make('until')->label(__('إلى تاريخ'))])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['from'] ?? null, fn (Builder $q, $d) => $q->whereDate('paid_at', '>=', $d))
                        ->when($data['until'] ?? null, fn (Builder $q, $d) => $q->whereDate('paid_at', '<=', $d))),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListPayments::route('/')];
    }
}
