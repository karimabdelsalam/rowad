<?php

namespace App\Filament\Widgets;

use App\Models\Installment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Unpaid installments due in the next two months (and any overdue), for the portal home pages. */
abstract class UpcomingPaymentsTable extends TableWidget
{
    protected static ?int $sort = 20;

    protected int|string|array $columnSpan = 'full';

    /** The portal user's installments (the portal resource's own scoped query). */
    abstract protected function scopedQuery(): Builder;

    abstract protected function heading(): string;

    protected function showTenant(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading($this->heading())
            ->query(fn () => $this->scopedQuery()->where('is_collected', false)->whereDate('due_date', '<=', today()->addMonths(2))
                ->with(['unit', 'contract.clients']))
            ->defaultSort('due_date')
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading(__('لا توجد دفعات مستحقة قريبًا'))
            ->emptyStateIcon('heroicon-o-check-badge')
            ->columns([
                TextColumn::make('due_date')->label(__('تاريخ الاستحقاق'))->date('Y-m-d')->weight('bold'),
                TextColumn::make('unit.title')->label(__('الوحدة')),
                TextColumn::make('tenant')->label(__('المستأجر'))->visible($this->showTenant())
                    ->state(fn (Installment $record) => $record->contract?->clients?->pluck('full_name')->implode('، ')),
                TextColumn::make('remaining')->label(__('المبلغ'))->numeric(2)
                    ->state(fn (Installment $record) => $record->remainingAmount()),
                TextColumn::make('state')->label(__('الحالة'))->badge()
                    ->state(fn (Installment $record) => $record->due_date->isBefore(today()) ? __('متأخرة') : __('قادمة'))
                    ->color(fn (Installment $record) => $record->due_date->isBefore(today()) ? 'danger' : 'warning'),
            ]);
    }
}
