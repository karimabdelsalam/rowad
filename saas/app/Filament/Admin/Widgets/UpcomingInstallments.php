<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Installment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class UpcomingInstallments extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('أقساط مستحقة خلال 14 يومًا أو متأخرة'))
            ->query(fn () => Installment::query()->with(['unit'])
                ->where('is_collected', false)->where('type', '!=', 'expenses')
                ->whereDate('due_date', '<=', today()->addDays(14)))
            ->defaultSort('due_date')
            ->paginated([5, 10])
            ->columns([
                TextColumn::make('due_date')->date('Y-m-d')->color(fn (Installment $r) => $r->due_date->isPast() ? 'danger' : null),
                TextColumn::make('unit.title'),
                TextColumn::make('amount')->numeric(2),
                TextColumn::make('remaining')->label(__('المتبقي'))->state(fn (Installment $r) => $r->remainingAmount())->numeric(2),
            ]);
    }

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->is_super_admin || $user->can('installments.view_any'));
    }
}
