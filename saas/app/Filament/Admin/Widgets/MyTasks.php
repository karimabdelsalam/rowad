<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Task;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Legacy dashboard "my tasks": open tasks assigned to the signed-in user, completed from the dashboard. */
class MyTasks extends TableWidget
{
    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 6];

    protected static ?int $sort = 8;

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('مهامي'))
            ->query(fn () => Task::query()->with('creator')->whereNull('completed_at')
                ->where(fn (Builder $query) => $query->where('assigned_to', auth()->id())->orWhere(fn (Builder $query) => $query->whereNull('assigned_to')->where('created_by', auth()->id()))))
            ->defaultSort('due_date')
            ->paginated([5, 10])
            ->emptyStateHeading(__('لا توجد مهام مفتوحة'))
            ->columns([
                TextColumn::make('content')->label(__('المهمة'))->limit(80)->wrap(),
                TextColumn::make('due_date')->label(__('تاريخ الاستحقاق'))->date('Y-m-d')->color(fn (Task $record) => $record->due_date?->isPast() ? 'danger' : null),
                TextColumn::make('creator.name')->label(__('من')),
            ])
            ->recordActions([
                Action::make('complete')
                    ->label(__('إنجاز'))
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->action(fn (Task $record) => $record->update(['completed_at' => now()])),
            ]);
    }
}
