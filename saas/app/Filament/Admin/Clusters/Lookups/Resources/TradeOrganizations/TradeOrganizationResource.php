<?php

namespace App\Filament\Admin\Clusters\Lookups\Resources\TradeOrganizations;

use App\Filament\Admin\Clusters\Lookups\LookupsCluster;
use App\Filament\Admin\Clusters\Lookups\Resources\TradeOrganizations\Pages\ManageTradeOrganizations;
use App\Models\Lookups\TradeOrganization;
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

class TradeOrganizationResource extends Resource
{
    protected static ?string $model = TradeOrganization::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static ?string $modelLabel = 'جهة تجارية';

    protected static ?string $pluralModelLabel = 'الجهات التجارية';

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
            'index' => ManageTradeOrganizations::route('/'),
        ];
    }
}
