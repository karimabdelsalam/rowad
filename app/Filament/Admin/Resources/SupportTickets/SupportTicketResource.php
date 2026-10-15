<?php

namespace App\Filament\Admin\Resources\SupportTickets;

use App\Filament\Admin\Resources\SupportTickets\Pages\EditSupportTicket;
use App\Filament\Admin\Resources\SupportTickets\Pages\ListSupportTickets;
use App\Filament\Admin\Resources\SupportTickets\Pages\ViewSupportTicket;
use App\Filament\Admin\Resources\SupportTickets\Schemas\SupportTicketForm;
use App\Filament\Admin\Resources\SupportTickets\Schemas\SupportTicketInfolist;
use App\Filament\Admin\Resources\SupportTickets\Tables\SupportTicketsTable;
use App\Models\SupportTicket;
use BackedEnum;
use App\Filament\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SupportTicketResource extends Resource
{
    protected static ?string $model = SupportTicket::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static string|\UnitEnum|null $navigationGroup = 'التشغيل';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'تذكرة دعم';

    protected static ?string $pluralModelLabel = 'تذاكر الدعم';

    protected static ?string $permission = 'support_tickets';

    public static function form(Schema $schema): Schema
    {
        return SupportTicketForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return SupportTicketInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SupportTicketsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupportTickets::route('/'),
            'view' => ViewSupportTicket::route('/{record}'),
            'edit' => EditSupportTicket::route('/{record}/edit'),
        ];
    }
}
