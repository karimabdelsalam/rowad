<?php

namespace App\Filament\Admin\Resources\Viewings\Pages;

use App\Enums\ViewingStatus as S;
use App\Filament\Admin\Resources\Viewings\ViewingResource;
use App\Models\Unit;
use App\Models\Viewing;
use App\Services\Viewings\ViewingSchedule;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ManageViewings extends ManageRecords
{
    protected static string $resource = ViewingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('موعد جديد'))->modalWidth('2xl')
                ->using(fn (array $data) => app(ViewingSchedule::class)->book(Unit::findOrFail($data['unit_id']), $data, 'staff')),
        ];
    }

    public function getTabs(): array
    {
        $now = ViewingSchedule::now();
        $today = fn (Builder $query) => $query->whereBetween('starts_at', [$now->startOfDay(), $now->endOfDay()]);
        $count = fn (callable $scope) => $scope(Viewing::query()->where('status', S::Booked))->count() ?: null;

        return [
            'today' => Tab::make(__('اليوم'))->badge($count($today))->modifyQueryUsing($today),
            'upcoming' => Tab::make(__('القادمة'))->badge($count(fn (Builder $query) => $query->where('starts_at', '>', $now->endOfDay())))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('starts_at', '>', $now->endOfDay())->where('status', S::Booked)),
            'past' => Tab::make(__('السابقة'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('starts_at', '<', $now->startOfDay())->reorder('starts_at', 'desc')),
            'all' => Tab::make(__('الكل')),
        ];
    }
}
