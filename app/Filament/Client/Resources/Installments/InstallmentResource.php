<?php

namespace App\Filament\Client\Resources\Installments;

use App\Filament\Client\Resources\Installments\Pages\ManageInstallments;
use App\Filament\PortalResource;
use App\Models\Installment;
use App\Models\RentContract;
use App\Models\SaleContract;
use App\Support\Portal;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Legacy ccp/payments: what the client owes and has paid. */
class InstallmentResource extends PortalResource
{
    protected static ?string $model = Installment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'دفعة';

    protected static ?string $pluralModelLabel = 'دفعاتي';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHasMorph('contract', [RentContract::class, SaleContract::class],
                fn (Builder $q) => $q->whereHas('clients', fn (Builder $q) => $q->whereKey(Portal::client()->getKey())))
            ->where('type', '!=', 'expenses')
            ->with(['unit', 'contract']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('due_date')
            ->striped()
            ->columns([
                TextColumn::make('due_date')->label(__('الاستحقاق'))->date('Y-m-d')->sortable()->weight('bold')
                    ->description(fn (Installment $record) => $record->is_collected ? null : $record->due_date?->locale(app()->getLocale())->diffForHumans())
                    ->color(fn (Installment $record) => ! $record->is_collected && $record->due_date?->isPast() ? 'danger' : null),
                TextColumn::make('unit.title')->label(__('الوحدة'))->weight('medium')
                    ->description(fn (Installment $record) => $record->contract?->number),
                TextColumn::make('type')->label(__('النوع'))->badge()->visibleFrom('lg'),
                TextColumn::make('amount')->label(__('المبلغ'))->numeric(2)->weight('bold')->visibleFrom('sm'),
                TextColumn::make('paid_amount')->visibleFrom('md')->label(__('المدفوع'))->state(fn (\App\Models\Installment $record) => $record->paidTotal())->numeric(2)->color('success'),
                TextColumn::make('remaining')->label(__('المتبقي'))->state(fn (Installment $record) => $record->remainingAmount())->numeric(2)->color(fn ($state) => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('state')->label(__('الحالة'))->badge()
                    ->state(fn (\App\Models\Installment $record) => $record->is_collected ? 'collected' : ($record->due_date?->isPast() ? 'late' : ((float) $record->paid_amount > 0 ? 'partial' : 'due')))
                    ->formatStateUsing(fn ($state) => ['collected' => __('مسدد'), 'late' => __('متأخر'), 'partial' => __('جزئي'), 'due' => __('مستحق')][$state])
                    ->color(fn ($state) => ['collected' => 'success', 'late' => 'danger', 'partial' => 'warning', 'due' => 'info'][$state])
                    ->icon(fn ($state) => ['collected' => Heroicon::OutlinedCheckCircle, 'late' => Heroicon::OutlinedExclamationCircle, 'partial' => Heroicon::OutlinedClock, 'due' => Heroicon::OutlinedCalendar][$state]),
            ])
            ->filters([TernaryFilter::make('is_collected')->label(__('مسدد'))->default(false)])
            // the pay button comes first so it is in view on a phone without scrolling sideways
            ->recordActionsPosition(\Filament\Tables\Enums\RecordActionsPosition::BeforeColumns)
            ->recordActions([
                \Filament\Actions\Action::make('pay')
                    ->label(__('ادفع الآن'))
                    ->icon(Heroicon::OutlinedCreditCard)
                    ->color('primary')
                    ->button()
                    ->visible(fn (Installment $record) => ! $record->is_collected && \App\Services\Payments\Payments::enabled())
                    ->url(fn (Installment $record) => app(\App\Services\Payments\Payments::class)->urlFor($record), shouldOpenInNewTab: true),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageInstallments::route('/')];
    }
}
