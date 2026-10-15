<?php

namespace App\Filament\Owner\Widgets;

use App\Filament\Owner\Resources\OwnerTransactions\OwnerTransactionResource;
use App\Filament\Owner\Resources\SupportTickets\SupportTicketResource;
use App\Filament\Widgets\PortalWelcomeBanner;
use App\Support\Portal;

class OwnerWelcome extends PortalWelcomeBanner
{
    protected function actions(): array
    {
        return [
            ['label' => __('كشف الحساب'), 'url' => OwnerTransactionResource::getUrl(), 'icon' => 'heroicon-o-scale'],
            ['label' => __('طباعة الكشف'), 'url' => route('documents.print', ['type' => 'owner_statement', 'id' => Portal::owner()->getKey()]),
                'icon' => 'heroicon-o-printer', 'external' => true],
            ['label' => __('الدعم الفني'), 'url' => SupportTicketResource::getUrl(), 'icon' => 'heroicon-o-lifebuoy'],
        ];
    }
}
