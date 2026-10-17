<?php

namespace App\Filament\Platform\Resources\ContactMessages;

use App\Filament\Platform\Nav;
use App\Models\ContactMessage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Messages from the official site's contact form: read, call back, mark handled. */
class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = Nav::Offices;

    protected static ?int $navigationSort = 9;

    protected static ?string $slug = 'contact-messages';

    protected static ?string $modelLabel = 'رسالة تواصل';

    protected static ?string $pluralModelLabel = 'رسائل التواصل';

    public static function getModelLabel(): string
    {
        return __(static::$modelLabel);
    }

    public static function getPluralModelLabel(): string
    {
        return __(static::$pluralModelLabel);
    }

    public static function getNavigationBadge(): ?string
    {
        $new = ContactMessage::where('status', 'new')->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('المرسل'))->columns(3)->schema([
                TextEntry::make('name')->label(__('الاسم'))->weight('bold'),
                TextEntry::make('company')->label(__('المؤسسة'))->placeholder('—'),
                TextEntry::make('city')->label(__('المدينة'))->placeholder('—'),
                TextEntry::make('phone')->label(__('الجوال'))->placeholder('—')->copyable()
                    ->url(fn (ContactMessage $r) => filled($r->phone) ? 'tel:'.preg_replace('/[^0-9+]/', '', $r->phone) : null),
                TextEntry::make('email')->label(__('البريد الإلكتروني'))->placeholder('—')->copyable()
                    ->url(fn (ContactMessage $r) => filled($r->email) ? 'mailto:'.$r->email : null),
                TextEntry::make('topic')->label(__('الموضوع'))->badge()->state(fn (ContactMessage $r) => $r->topicLabel()),
            ]),
            Section::make(__('الرسالة'))->schema([
                TextEntry::make('message')->hiddenLabel()->extraAttributes(['style' => 'white-space:pre-line;line-height:1.9']),
                TextEntry::make('created_at')->label(__('أُرسلت'))->dateTime('Y-m-d H:i'),
            ]),
            Section::make(__('المتابعة'))->columns(2)->schema([
                TextEntry::make('status')->label(__('الحالة'))->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'handled' ? __('تمت المتابعة') : __('جديدة'))
                    ->color(fn (string $state) => $state === 'handled' ? 'success' : 'warning'),
                TextEntry::make('handled_by')->label(__('تابعها'))->placeholder('—')
                    ->suffix(fn (ContactMessage $r) => $r->handled_at ? ' · '.$r->handled_at->format('Y-m-d H:i') : ''),
                TextEntry::make('notes')->label(__('ملاحظات'))->placeholder('—')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label(__('الاسم'))->weight('bold')->searchable()
                    ->description(fn (ContactMessage $r) => $r->company),
                TextColumn::make('phone')->label(__('الجوال'))->searchable()->placeholder('—')
                    ->description(fn (ContactMessage $r) => $r->email),
                TextColumn::make('topic')->label(__('الموضوع'))->badge()->color('gray')->formatStateUsing(fn ($state, ContactMessage $r) => $r->topicLabel()),
                TextColumn::make('message')->label(__('الرسالة'))->limit(70)->searchable()->wrap(),
                TextColumn::make('status')->label(__('الحالة'))->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'handled' ? __('تمت المتابعة') : __('جديدة'))
                    ->color(fn (string $state) => $state === 'handled' ? 'success' : 'warning'),
                TextColumn::make('created_at')->label(__('أُرسلت'))->since()->sortable()
                    ->tooltip(fn (ContactMessage $r) => $r->created_at?->format('Y-m-d H:i')),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('الحالة'))->options(['new' => __('جديدة'), 'handled' => __('تمت المتابعة')]),
                SelectFilter::make('topic')->label(__('الموضوع'))->options(collect(ContactMessage::TOPICS)->map(fn ($l) => __($l))->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                self::handleAction(),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function handleAction(): Action
    {
        return Action::make('handle')
            ->label(__('تمت المتابعة'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (ContactMessage $record) => $record->status !== 'handled')
            ->schema([Textarea::make('notes')->label(__('ملاحظات المتابعة'))->rows(3)->maxLength(2000)])
            ->action(fn (ContactMessage $record, array $data) => $record->forceFill([
                'status' => 'handled', 'handled_at' => now(), 'handled_by' => auth()->user()?->name, 'notes' => $data['notes'] ?? null,
            ])->save());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContactMessages::route('/'),
            'view' => Pages\ViewContactMessage::route('/{record}'),
        ];
    }
}
