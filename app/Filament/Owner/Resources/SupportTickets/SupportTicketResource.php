<?php

namespace App\Filament\Owner\Resources\SupportTickets;

use App\Filament\Concerns\PortalSupportTickets;
use App\Filament\Owner\Resources\SupportTickets\Pages\ManageSupportTickets;
use App\Filament\PortalResource;
use App\Models\SupportTicket;
use App\Support\Portal;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class SupportTicketResource extends PortalResource
{
    use PortalSupportTickets;

    protected static ?string $model = SupportTicket::class;

    protected static bool $portalCanCreate = true;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'تذكرة دعم';

    protected static ?string $pluralModelLabel = 'الدعم الفني';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('owner_id', Portal::owner()->getKey())->with('replies');
    }

    protected static function ticketUnits(): array
    {
        return Portal::ownerUnits(Portal::owner())->pluck('title', 'id')->all();
    }

    public static function getPages(): array
    {
        return ['index' => ManageSupportTickets::route('/')];
    }
}
