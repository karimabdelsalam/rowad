<?php

namespace App\Filament\Owner\Widgets;

use App\Filament\Owner\Resources\Installments\InstallmentResource;
use App\Filament\Widgets\UpcomingPaymentsTable;
use Illuminate\Database\Eloquent\Builder;

class UpcomingCollections extends UpcomingPaymentsTable
{
    protected function scopedQuery(): Builder
    {
        return InstallmentResource::getEloquentQuery();
    }

    protected function heading(): string
    {
        return __('التحصيلات القادمة لوحداتك');
    }

    protected function showTenant(): bool
    {
        return true;
    }
}
