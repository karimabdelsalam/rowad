<?php

namespace App\Filament\Admin\Resources\MaintenanceRequests\Pages;

use App\Enums\MaintenanceStatus as S;
use App\Filament\Admin\Resources\MaintenanceRequests\MaintenanceRequestResource;
use App\Models\MaintenanceRequest;
use App\Services\Maintenance\MaintenanceService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListMaintenanceRequests extends ListRecords
{
    protected static string $resource = MaintenanceRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('طلب صيانة جديد'))
                ->modalWidth('2xl')
                ->using(fn (array $data) => app(MaintenanceService::class)->open($data)),
        ];
    }

    public function getTabs(): array
    {
        $count = fn (array $statuses) => MaintenanceRequest::query()->whereIn('status', $statuses)->count() ?: null;

        return [
            'open' => Tab::make(__('المفتوحة'))->badge($count(S::OPEN))
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', S::OPEN)),
            'new' => Tab::make(__('جديدة'))->badge($count([S::New->value]))->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', S::New)),
            'approval' => Tab::make(__('بانتظار المالك'))->badge($count([S::AwaitingApproval->value]))->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', S::AwaitingApproval)),
            'completed' => Tab::make(__('المنجزة'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', S::Completed)),
            'all' => Tab::make(__('الكل')),
        ];
    }
}
