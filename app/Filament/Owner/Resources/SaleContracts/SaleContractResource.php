<?php

namespace App\Filament\Owner\Resources\SaleContracts;

use App\Filament\Owner\Resources\SaleContracts\Pages\ManageSaleContracts;
use App\Filament\PortalResource;
use App\Models\SaleContract;
use App\Support\Portal;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Sale contracts on the owner's units (legacy ownerarea units/sell). */
class SaleContractResource extends PortalResource
{
    protected static ?string $model = SaleContract::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'عقد بيع';

    protected static ?string $pluralModelLabel = 'عقود البيع';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('unit.owners', fn (Builder $q) => $q->whereKey(Portal::owner()->getKey()))
            ->with(['unit', 'clients']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('number'),
            TextEntry::make('unit.title'),
            TextEntry::make('contract_date')->date('Y-m-d'),
            TextEntry::make('total_amount')->numeric(2),
            TextEntry::make('payment_type')->badge(),
            TextEntry::make('status')->badge(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number'),
                TextColumn::make('unit.title'),
                TextColumn::make('clients.full_name')->label(__('المشترون'))->listWithLineBreaks(),
                TextColumn::make('contract_date')->date('Y-m-d'),
                TextColumn::make('total_amount')->numeric(2),
                TextColumn::make('payment_type')->badge(),
                TextColumn::make('status')->badge(),
            ])
            ->recordActions([
                \Filament\Actions\Action::make('print')
                    ->label(__('طباعة'))
                    ->icon(\Filament\Support\Icons\Heroicon::OutlinedPrinter)
                    ->url(fn ($record) => route('documents.print', ['type' => 'sale_contract', 'id' => $record->getKey()]))
                    ->openUrlInNewTab(),
                \App\Filament\Support\DocumentActions::pdf('sale_contract', portal: true),
ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSaleContracts::route('/')];
    }
}
