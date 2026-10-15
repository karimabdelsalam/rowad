<?php

namespace App\Filament\Admin\Resources\CompanyPartners;

use App\Enums\IdType;
use App\Filament\Admin\Resources\CompanyPartners\Pages\ManageCompanyPartners;
use App\Models\CompanyPartner;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use App\Filament\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CompanyPartnerResource extends Resource
{
    protected static ?string $model = CompanyPartner::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|\UnitEnum|null $navigationGroup = 'الإعدادات';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'شريك';

    protected static ?string $pluralModelLabel = 'شركاء المنشأة';

    protected static ?string $permission = 'company_partners';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('first_name')
                    ->required(),
                TextInput::make('father_name'),
                TextInput::make('grandfather_name'),
                TextInput::make('family_name'),
                TextInput::make('full_name')
                    ->required(),
                Select::make('id_type')
                    ->options(IdType::class),
                TextInput::make('id_number'),
                TextInput::make('id_source'),
                DatePicker::make('id_issue_date'),
                TextInput::make('nationality'),
                TextInput::make('mobile'),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('home_phone')
                    ->tel(),
                TextInput::make('work'),
                TextInput::make('work_phone')
                    ->tel(),
                Textarea::make('address')
                    ->columnSpanFull(),
                TextInput::make('share_percent')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('city'),
                TextInput::make('postal_code'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('first_name')
                    ->searchable(),
                TextColumn::make('father_name')
                    ->searchable(),
                TextColumn::make('grandfather_name')
                    ->searchable(),
                TextColumn::make('family_name')
                    ->searchable(),
                TextColumn::make('full_name')
                    ->searchable(),
                TextColumn::make('id_type')
                    ->badge()
                    ->searchable(),
                TextColumn::make('id_number')
                    ->searchable(),
                TextColumn::make('id_source')
                    ->searchable(),
                TextColumn::make('id_issue_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('nationality')
                    ->searchable(),
                TextColumn::make('mobile')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('home_phone')
                    ->searchable(),
                TextColumn::make('work')
                    ->searchable(),
                TextColumn::make('work_phone')
                    ->searchable(),
                TextColumn::make('share_percent')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('city')
                    ->searchable(),
                TextColumn::make('postal_code')
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
            'index' => ManageCompanyPartners::route('/'),
        ];
    }
}
