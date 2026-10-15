<?php

namespace App\Filament\Client\Resources\SupportTickets;

use App\Filament\Client\Resources\SupportTickets\Pages\ManageSupportTickets;
use App\Filament\Concerns\PortalSupportTickets;
use App\Filament\PortalResource;
use App\Models\SupportTicket;
use App\Models\Unit;
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

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'تذكرة دعم';

    protected static ?string $pluralModelLabel = 'الدعم الفني';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('client_id', Portal::client()->getKey())->with('replies');
    }

    protected static function ticketUnits(): array
    {
        $client = Portal::client();

        return Unit::query()
            ->where(fn ($q) => $q
                ->whereHas('rentContracts.clients', fn ($q) => $q->whereKey($client->getKey()))
                ->orWhereHas('saleContracts.clients', fn ($q) => $q->whereKey($client->getKey())))
            ->pluck('title', 'id')->all();
    }

    public static function getPages(): array
    {
        return ['index' => ManageSupportTickets::route('/')];
    }
}
