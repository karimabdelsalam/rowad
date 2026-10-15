<?php

namespace App\Filament\Owner\Resources\RentContracts;

use App\Filament\Owner\Resources\RentContracts\Pages\ManageRentContracts;
use App\Filament\PortalResource;
use App\Models\RentContract;
use App\Support\Portal;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Rent contracts on the owner's units (legacy ownerarea units/rent). */
class RentContractResource extends PortalResource
{
    protected static ?string $model = RentContract::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'عقد إيجار';

    protected static ?string $pluralModelLabel = 'عقود الإيجار';

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
            TextEntry::make('clients.full_name')->label(__('المستأجرون'))->listWithLineBreaks(),
            TextEntry::make('status')->badge(),
            TextEntry::make('start_date')->date('Y-m-d'),
            TextEntry::make('end_date')->date('Y-m-d'),
            TextEntry::make('payment_cycle')->badge(),
            TextEntry::make('rent_amount')->numeric(2),
            TextEntry::make('next_payment_date')->date('Y-m-d'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('start_date', 'desc')
            ->columns([
                TextColumn::make('number')->searchable(),
                TextColumn::make('unit.title'),
                TextColumn::make('clients.full_name')->label(__('المستأجرون'))->listWithLineBreaks(),
                TextColumn::make('start_date')->date('Y-m-d'),
                TextColumn::make('end_date')->date('Y-m-d'),
                TextColumn::make('rent_amount')->numeric(2),
                TextColumn::make('status')->badge(),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRentContracts::route('/')];
    }
}
