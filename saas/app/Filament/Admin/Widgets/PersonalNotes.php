<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Note;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Legacy dashboard personal notes (notes table): private to each user. */
class PersonalNotes extends TableWidget
{
    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 6];

    protected static ?int $sort = 10;

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('ملاحظاتي'))
            ->query(fn () => Note::query()->where('user_id', auth()->id()))
            ->defaultSort('id', 'desc')
            ->paginated([5, 10])
            ->emptyStateHeading(__('لا توجد ملاحظات'))
            ->columns([
                TextColumn::make('content')->label(__('الملاحظة'))->wrap(),
                TextColumn::make('created_at')->label(__('التاريخ'))->date('Y-m-d'),
            ])
            ->headerActions([
                Action::make('addNote')
                    ->label(__('إضافة ملاحظة'))
                    ->icon(Heroicon::OutlinedPlus)
                    ->schema([Textarea::make('content')->label(__('الملاحظة'))->required()->rows(3)->maxLength(5000)])
                    ->action(fn (array $data) => Note::create(['user_id' => auth()->id(), 'content' => $data['content']])),
            ])
            ->recordActions([
                DeleteAction::make()->authorize(fn (Note $record) => $record->user_id === auth()->id()),
            ]);
    }
}
