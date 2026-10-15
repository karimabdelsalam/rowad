<?php

namespace App\Filament\Admin\Resources\Letters;

use App\Enums\Calendar;
use App\Filament\Admin\Resources\Letters\Pages\ManageLetters;
use App\Models\Letter;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use App\Filament\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LetterResource extends Resource
{
    protected static ?string $model = Letter::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelopeOpen;

    protected static string|\UnitEnum|null $navigationGroup = 'العقود';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'خطاب';

    protected static ?string $pluralModelLabel = 'الخطابات';

    protected static ?string $permission = 'letters';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('type')
                    ->required(),
                Select::make('unit_id')
                    ->relationship('unit', 'title'),
                Select::make('client_id')
                    ->relationship('client', 'id'),
                Select::make('calendar')
                    ->options(Calendar::class)
                    ->default('gregorian')
                    ->required(),
                DatePicker::make('letter_date')
                    ->required(),
                Toggle::make('is_official')
                    ->required(),
                TextInput::make('rent_raise_amount')
                    ->numeric(),
                Textarea::make('data')
                    ->columnSpanFull(),
                Toggle::make('marks_unit_reserved')
                    ->required(),
                TextInput::make('created_by')
                    ->numeric(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->searchable(),
                TextColumn::make('unit.title')
                    ->searchable(),
                TextColumn::make('client.full_name')
                    ->label(__('العميل'))
                    ->searchable(),
                TextColumn::make('calendar')
                    ->badge()
                    ->searchable(),
                TextColumn::make('letter_date')
                    ->date()
                    ->sortable(),
                IconColumn::make('is_official')
                    ->boolean(),
                TextColumn::make('rent_raise_amount')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('marks_unit_reserved')
                    ->boolean(),
                TextColumn::make('created_by')
                    ->numeric()
                    ->sortable(),
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
            'index' => ManageLetters::route('/'),
        ];
    }
}
