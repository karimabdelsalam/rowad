<?php

namespace App\Filament\Admin\Clusters\Lookups\Resources\TransactionTypes;

use App\Filament\Admin\Clusters\Lookups\Resources\TransactionTypes\Pages\ManageTransactionTypes;
use App\Models\Lookups\TransactionType;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use App\Filament\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TransactionTypeResource extends Resource
{
    protected static ?string $model = TransactionType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $modelLabel = 'نوع حركة';

    protected static ?string $pluralModelLabel = 'أنواع حركات الملاك';

    protected static ?string $permission = 'lookups';

    protected static string|\UnitEnum|null $navigationGroup = 'قواعد البيانات';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                TextInput::make('title')
                    ->required(),
                Toggle::make('is_income')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable(),
                TextColumn::make('title')
                    ->searchable(),
                IconColumn::make('is_income')
                    ->boolean(),
                IconColumn::make('is_system')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /** Codes used by the owner-ledger engine (payment, commission, mancost...) are locked. */
    public static function getAuthorizationResponse(string|\UnitEnum $action, ?\Illuminate\Database\Eloquent\Model $record = null): \Illuminate\Auth\Access\Response
    {
        if ($record?->is_system && in_array($action, ['update', 'delete'], true)) {
            return \Illuminate\Auth\Access\Response::deny();
        }

        return parent::getAuthorizationResponse($action, $record);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTransactionTypes::route('/'),
        ];
    }
}
