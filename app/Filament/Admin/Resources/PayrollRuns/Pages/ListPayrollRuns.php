<?php

namespace App\Filament\Admin\Resources\PayrollRuns\Pages;

use App\Exceptions\BusinessRuleException;
use App\Filament\Admin\Resources\Hr;
use App\Filament\Admin\Resources\PayrollRuns\PayrollRunResource;
use App\Services\Hr\Payroll;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListPayrollRuns extends ListRecords
{
    protected static string $resource = PayrollRunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label(__('مسيّر جديد'))
                ->icon(Heroicon::OutlinedPlus)
                ->visible(fn () => Hr::can('payroll.create'))
                ->modalDescription(__('يُبنى المسيّر من رواتب الموظفين على رأس العمل، مع حسومات الشهر وأقساط السلف وإجازات بلا راتب وحصص التأمينات. يمكن تعديل السطور قبل الاعتماد.'))
                ->schema([
                    Select::make('month')->label(__('الشهر'))->options(Hr::months())->default((int) now()->month)->required(),
                    TextInput::make('year')->label(__('السنة'))->integer()->default((int) now()->year)->required()->minValue(2000)->maxValue(2100),
                ])
                ->action(function (array $data) {
                    try {
                        $run = app(Payroll::class)->generate((int) $data['year'], (int) $data['month']);
                    } catch (BusinessRuleException $e) {
                        Notification::make()->danger()->title($e->getMessage())->send();

                        return;
                    }
                    Notification::make()->success()->title(__('أُنشئ مسيّر :p', ['p' => $run->periodLabel()]))->send();
                    $this->redirect(PayrollRunResource::getUrl('view', ['record' => $run]));
                }),
        ];
    }
}
