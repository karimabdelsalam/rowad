<?php

namespace App\Filament\Admin\Clusters\Lookups\Resources\GovernmentOrganizations;

use App\Filament\Admin\Clusters\Lookups\LookupsCluster;
use App\Filament\Admin\Clusters\Lookups\Resources\GovernmentOrganizations\Pages\ManageGovernmentOrganizations;
use App\Models\Lookups\GovernmentOrganization;
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

class GovernmentOrganizationResource extends Resource
{
    protected static ?string $model = GovernmentOrganization::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static ?string $modelLabel = 'جهة حكومية';

    protected static ?string $pluralModelLabel = 'الجهات الحكومية';

    protected static ?string $permission = 'lookups';

    protected static ?string $cluster = LookupsCluster::class;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required(),
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
            'index' => ManageGovernmentOrganizations::route('/'),
        ];
    }
}
