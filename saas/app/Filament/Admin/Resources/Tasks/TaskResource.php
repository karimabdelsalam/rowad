<?php

namespace App\Filament\Admin\Resources\Tasks;

use App\Filament\Admin\Resources\Tasks\Pages\ManageTasks;
use App\Filament\Resource;
use App\Models\Task;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Legacy task module: assign to a staff member, complete / un-complete; open tasks show on the dashboard. */
class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

    protected static string|\UnitEnum|null $navigationGroup = 'التشغيل';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'مهمة';

    protected static ?string $pluralModelLabel = 'المهام';

    protected static ?string $permission = 'tasks';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('content')->label(__('المهمة'))->required()->rows(3)->columnSpanFull(),
            Select::make('assigned_to')->label(__('مسندة إلى'))
                ->options(fn () => User::where('type', 'staff')->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                ->default(fn () => auth()->id())->searchable(),
            DatePicker::make('due_date')->label(__('تاريخ الاستحقاق')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['creator', 'assignee']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('content')->label(__('المهمة'))->limit(80)->wrap()->searchable(),
                TextColumn::make('assignee.name')->label(__('مسندة إلى'))->placeholder('—'),
                TextColumn::make('creator.name')->label(__('من')),
                TextColumn::make('due_date')->label(__('تاريخ الاستحقاق'))->date('Y-m-d')->sortable()
                    ->color(fn (Task $record) => $record->completed_at === null && $record->due_date?->isPast() ? 'danger' : null),
                TextColumn::make('completed_at')->label(__('أُنجزت'))->dateTime('Y-m-d H:i')->placeholder(__('مفتوحة'))->sortable(),
                TextColumn::make('created_at')->dateTime('Y-m-d')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('completed_at')->label(__('الحالة'))->nullable()
                    ->trueLabel(__('منجزة'))->falseLabel(__('مفتوحة'))->default(false),
                Filter::make('mine')->label(__('مهامي فقط'))
                    ->query(fn (Builder $query) => $query->where('assigned_to', auth()->id())),
                SelectFilter::make('assigned_to')->label(__('مسندة إلى'))->relationship('assignee', 'name'),
            ])
            ->recordActions([
                Action::make('toggleDone')
                    ->label(fn (Task $record) => $record->completed_at ? __('إلغاء الإنجاز') : __('إنجاز'))
                    ->icon(fn (Task $record) => $record->completed_at ? Heroicon::OutlinedArrowUturnLeft : Heroicon::OutlinedCheck)
                    ->color(fn (Task $record) => $record->completed_at ? 'gray' : 'success')
                    ->action(fn (Task $record) => $record->update(['completed_at' => $record->completed_at ? null : now()])),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTasks::route('/'),
        ];
    }
}
