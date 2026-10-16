<?php

namespace App\Filament\Platform\Resources\Admins;

use App\Filament\Platform\Nav;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Who can open this console (central users with is_super_admin). */
class AdminResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = Nav::Platform;

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'admins';

    protected static ?string $modelLabel = 'مدير منصة';

    protected static ?string $pluralModelLabel = 'مديرو المنصة';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('is_super_admin', true);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(__('الحساب'))->columns(2)->schema([
                TextInput::make('name')->label(__('الاسم'))->required()->maxLength(190),
                TextInput::make('email')->label(__('البريد الإلكتروني'))->email()->required()->maxLength(190)->unique(ignoreRecord: true)->extraInputAttributes(['dir' => 'ltr']),
                TextInput::make('password')->label(__('كلمة المرور'))->password()->revealable()->minLength(10)
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn ($state) => filled($state))
                    ->helperText(fn (string $operation) => $operation === 'edit' ? __('اتركها فارغة للإبقاء على كلمة المرور الحالية.') : null),
                Toggle::make('is_active')->label(__('مفعل'))->default(true)->inline(false)
                    ->disabled(fn (?User $record) => $record !== null && $record->is(auth()->user()))
                    ->helperText(fn (?User $record) => $record !== null && $record->is(auth()->user()) ? __('لا يمكنك إيقاف حسابك.') : null),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label(__('الاسم'))->weight('bold')->searchable()->sortable()
                    ->description(fn (User $r) => $r->email),
                IconColumn::make('is_active')->label(__('مفعل'))->boolean()->alignCenter(),
                TextColumn::make('last_login_at')->label(__('آخر دخول'))->since()->sortable()->placeholder(__('لم يدخل بعد')),
                TextColumn::make('created_at')->label(__('أُضيف في'))->date('Y-m-d')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('toggle')
                    ->label(fn (User $r) => $r->is_active ? __('إيقاف') : __('تفعيل'))
                    ->icon(fn (User $r) => $r->is_active ? Heroicon::OutlinedPauseCircle : Heroicon::OutlinedPlayCircle)
                    ->color(fn (User $r) => $r->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->visible(fn (User $r) => ! $r->is(auth()->user()))
                    ->action(fn (User $r) => $r->forceFill(['is_active' => ! $r->is_active])->save()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdmins::route('/'),
            'create' => Pages\CreateAdmin::route('/create'),
            'edit' => Pages\EditAdmin::route('/{record}/edit'),
        ];
    }
}
