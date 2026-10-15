<?php

namespace App\Filament\Admin\Resources\ActivityLogs;

use App\Filament\Resource;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Spatie\Activitylog\Models\Activity;

/** Legacy manager/logs: who did what, filterable by user and module. Read-only. */
class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = 90;

    protected static ?string $modelLabel = 'عملية';

    protected static ?string $pluralModelLabel = 'سجل العمليات';

    protected static ?string $permission = 'activity_log';

    protected static ?string $slug = 'activity-log';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canView($record): bool
    {
        return static::canViewAny();
    }

    /** Morph alias → screen name, taken from the panel's resources ("unit" → "وحدة"). */
    public static function subjectLabels(): array
    {
        static $labels = null;

        return $labels ??= collect(Filament::getCurrentOrDefaultPanel()->getResources())
            ->mapWithKeys(fn (string $resource) => [(new ($resource::getModel()))->getMorphClass() => $resource::getModelLabel()])
            ->filter(fn ($label, $alias) => Relation::getMorphedModel($alias) !== null)
            ->sort()
            ->all();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('causer'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label(__('الوقت'))->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('causer.name')->label(__('المستخدم'))->placeholder(__('النظام')),
                TextColumn::make('event')->label(__('العملية'))->badge()
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        'created' => __('إضافة'), 'updated' => __('تعديل'), 'deleted' => __('حذف'), 'restored' => __('استرجاع'), default => $state,
                    })
                    ->color(fn (?string $state) => match ($state) { 'created' => 'success', 'deleted' => 'danger', default => 'gray' }),
                TextColumn::make('subject_type')->label(__('القسم'))->formatStateUsing(fn (?string $state) => static::subjectLabels()[$state] ?? $state),
                TextColumn::make('subject_id')->label(__('رقم السجل'))->prefix('#'),
                TextColumn::make('description')->label(__('الوصف'))->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('causer_id')->label(__('المستخدم'))
                    ->options(fn () => User::where('type', 'staff')->orderBy('name')->pluck('name', 'id'))
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null,
                        fn (Builder $query, $id) => $query->where('causer_type', (new User)->getMorphClass())->where('causer_id', $id))),
                SelectFilter::make('subject_type')->label(__('القسم'))->options(fn () => static::subjectLabels()),
                SelectFilter::make('event')->label(__('العملية'))
                    ->options(['created' => __('إضافة'), 'updated' => __('تعديل'), 'deleted' => __('حذف'), 'restored' => __('استرجاع')]),
                Filter::make('created_at')
                    ->schema([DatePicker::make('from')->label(__('من تاريخ')), DatePicker::make('until')->label(__('إلى تاريخ'))])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, $date) => $query->whereDate('created_at', '<=', $date))),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('created_at')->label(__('الوقت'))->dateTime('Y-m-d H:i:s'),
            TextEntry::make('causer.name')->label(__('المستخدم'))->placeholder(__('النظام')),
            TextEntry::make('subject_type')->label(__('القسم'))->formatStateUsing(fn (?string $state) => static::subjectLabels()[$state] ?? $state),
            TextEntry::make('subject_id')->label(__('رقم السجل')),
            KeyValueEntry::make('properties.old')->label(__('القيم السابقة'))->columnSpanFull()
                ->state(fn (Activity $record) => self::flatten($record->properties['old'] ?? [])),
            KeyValueEntry::make('properties.attributes')->label(__('القيم الجديدة'))->columnSpanFull()
                ->state(fn (Activity $record) => self::flatten($record->properties['attributes'] ?? [])),
        ]);
    }

    /** @return array<string, string> */
    private static function flatten(mixed $values): array
    {
        return collect((array) $values)->map(fn ($v) => is_scalar($v) || $v === null ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE))->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
            'view' => Pages\ViewActivityLog::route('/{record}'),
        ];
    }
}
