<?php

namespace App\Filament\Admin\Resources\Branches;

use App\Filament\Admin\Resources\Branches\Pages\ManageBranches;
use App\Filament\Resource;
use App\Models\Branch;
use App\Support\Branches;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Branches of the office (enterprise plan): staff tied to a branch see only its properties and money. */
class BranchResource extends Resource
{
    protected static ?string $model = Branch::class;

    protected static ?string $permission = 'branches';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'فرع';

    protected static ?string $pluralModelLabel = 'الفروع';

    public static function canAccess(): bool
    {
        return Branches::enabled() && Branches::current() === null && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label(__('اسم الفرع'))->required()->maxLength(190),
            Select::make('city_id')->label(__('المدينة'))->relationship('city', 'name')->searchable()->preload(),
            TextInput::make('phone')->label(__('الهاتف'))->tel()->maxLength(30),
            TextInput::make('address')->label(__('العنوان'))->maxLength(255),
            Toggle::make('is_active')->label(__('نشط'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('الفرع'))->weight('bold')->searchable()
                    ->description(fn (Branch $r) => $r->address),
                TextColumn::make('city.name')->label(__('المدينة'))->placeholder('-'),
                TextColumn::make('units_count')->label(__('الوحدات'))->counts('units')->badge()->color('info'),
                TextColumn::make('users_count')->label(__('الموظفون'))->counts('users')->badge()->color('gray'),
                TextColumn::make('phone')->label(__('الهاتف'))->placeholder('-'),
                IconColumn::make('is_active')->label(__('نشط'))->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()
                ->modalDescription(__('تبقى الوحدات والموظفون، ويصبحون بلا فرع (يراهم المدير فقط).'))]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageBranches::route('/')];
    }
}
