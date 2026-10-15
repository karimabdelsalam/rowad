<?php

namespace App\Filament\Platform\Widgets;

use App\Filament\Platform\Resources\Tenants\TenantResource;
use App\Models\Tenant;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Who to call this week: upgrade requests, trials about to end, and offices that have not paid. */
class OfficesNeedingAttention extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('مؤسسات تحتاج متابعة'))
            ->query(fn () => Tenant::query()->with(['plan', 'domains'])
                ->where(fn (Builder $q) => $q
                    ->whereNotNull('data->upgrade_request')
                    ->orWhere(fn (Builder $q) => $q->where('status', Tenant::STATUS_TRIAL)->where('trial_ends_at', '<=', now()->addDays(7)))
                    ->orWhere('status', Tenant::STATUS_PAST_DUE)))
            ->defaultSort('trial_ends_at')
            ->paginated([5, 10])
            ->emptyStateHeading(__('لا شيء يحتاج متابعة الآن'))
            ->emptyStateIcon('heroicon-o-check-circle')
            ->recordUrl(fn (Tenant $record) => TenantResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('name')->label(__('المؤسسة'))->weight('bold')->description(fn (Tenant $record) => $record->domains->first()?->domain),
                TextColumn::make('reason')->label(__('السبب'))->badge()
                    ->state(fn (Tenant $record) => match (true) {
                        filled($record->upgrade_request) => __('طلب ترقية إلى :plan', ['plan' => $record->upgrade_request['plan'] ?? '-']),
                        $record->status === Tenant::STATUS_PAST_DUE => __('متأخر السداد'),
                        $record->trial_ends_at?->isPast() => __('انتهت التجربة'),
                        default => __('تنتهي التجربة'),
                    })
                    ->color(fn (Tenant $record) => filled($record->upgrade_request) ? 'success' : ($record->status === Tenant::STATUS_PAST_DUE || $record->trial_ends_at?->isPast() ? 'danger' : 'warning')),
                TextColumn::make('plan.name_ar')->label(__('الباقة'))->placeholder('-'),
                TextColumn::make('trial_ends_at')->label(__('نهاية التجربة'))->date('Y-m-d')->placeholder('-'),
                TextColumn::make('phone')->label(__('الجوال'))->placeholder('-'),
                TextColumn::make('email')->label(__('البريد الإلكتروني'))->placeholder('-'),
            ]);
    }
}
