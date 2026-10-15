<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\ContractStatus;
use App\Models\RentContract;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ExpiringContracts extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('عقود تنتهي خلال 60 يومًا'))
            ->query(fn () => RentContract::query()->with(['unit', 'clients'])
                ->where('status', ContractStatus::Active)
                ->whereBetween('end_date', [today(), today()->addDays(60)]))
            ->defaultSort('end_date')
            ->paginated([5, 10])
            ->columns([
                TextColumn::make('number'),
                TextColumn::make('unit.title'),
                TextColumn::make('clients.full_name')->label(__('المستأجرون'))->listWithLineBreaks(),
                TextColumn::make('end_date')->date('Y-m-d'),
            ]);
    }

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->is_super_admin || $user->can('rent_contracts.view_any'));
    }
}
