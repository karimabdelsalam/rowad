<?php

namespace App\Filament\Client\Widgets;

use App\Enums\ContractStatus;
use App\Filament\Client\Resources\Installments\InstallmentResource;
use App\Filament\Client\Resources\SupportTickets\SupportTicketResource;
use App\Filament\Widgets\PortalWelcomeBanner;
use App\Support\Portal;

class ClientWelcome extends PortalWelcomeBanner
{
    protected function actions(): array
    {
        $contract = Portal::client()->rentContracts()->where('status', ContractStatus::Active)->latest('start_date')->first();

        return array_values(array_filter([
            ['label' => __('دفعاتي'), 'url' => InstallmentResource::getUrl(), 'icon' => 'heroicon-o-banknotes'],
            $contract ? ['label' => __('طباعة عقدي'), 'url' => route('documents.print', ['type' => 'rent_contract', 'id' => $contract->getKey()]),
                'icon' => 'heroicon-o-printer', 'external' => true] : null,
            ['label' => __('طلب صيانة'), 'url' => SupportTicketResource::getUrl(), 'icon' => 'heroicon-o-wrench-screwdriver'],
        ]));
    }
}
