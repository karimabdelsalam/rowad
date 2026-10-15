<?php

namespace App\Filament\Client\Widgets;

use App\Filament\Client\Resources\Installments\InstallmentResource;
use App\Filament\Widgets\UpcomingPaymentsTable;
use Illuminate\Database\Eloquent\Builder;

class UpcomingPayments extends UpcomingPaymentsTable
{
    protected function scopedQuery(): Builder
    {
        return InstallmentResource::getEloquentQuery();
    }

    protected function heading(): string
    {
        return __('دفعاتك القادمة');
    }
}
