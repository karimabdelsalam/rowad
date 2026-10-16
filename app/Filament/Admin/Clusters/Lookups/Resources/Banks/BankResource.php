<?php

namespace App\Filament\Admin\Clusters\Lookups\Resources\Banks;

use App\Filament\Admin\Clusters\Lookups\Resources\Banks\Pages\ManageBanks;
use App\Models\Lookups\Bank;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use App\Filament\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BankResource extends Resource
{
    protected static ?string $model = Bank::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $modelLabel = 'بنك';

    protected static ?string $pluralModelLabel = 'البنوك';

    protected static ?string $permission = 'lookups';

    protected static string|\UnitEnum|null $navigationGroup = 'قواعد البيانات';

    protected static ?int $navigationSort = 9;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required(),
                TextInput::make('swift')->label(__('رمز SWIFT/BIC'))->maxLength(11)->extraInputAttributes(['dir' => 'ltr'])->helperText(__('مطلوب في ملف حماية الأجور، مثل RJHISARI')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable(),
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

    public static function getPages(): array
    {
        return [
            'index' => ManageBanks::route('/'),
        ];
    }
}
