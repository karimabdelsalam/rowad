<?php

namespace App\Filament\Admin\Resources\EndOfServiceSettlements\Pages;

use App\Filament\Admin\Resources\EndOfServiceSettlements\EndOfServiceSettlementResource;
use App\Filament\Admin\Resources\Hr;
use App\Models\BankAccount;
use App\Models\EndOfServiceSettlement;
use App\Services\Finance\VoucherService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewEndOfServiceSettlement extends ViewRecord
{
    protected static string $resource = EndOfServiceSettlementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pay')->label(__('صرف التسوية'))->icon(Heroicon::OutlinedBanknotes)->color('success')
                ->visible(fn () => $this->getRecord()->status !== 'paid' && Hr::can('end_of_service.pay'))
                ->modalDescription(__('يصدر سند صرف بصافي التسوية، وتُسجَّل نهاية خدمة الموظف بتاريخ التسوية.'))
                ->schema([
                    Select::make('bank_account_id')->label(__('من الحساب البنكي'))->options(fn () => BankAccount::query()->pluck('holder_name', 'id'))->placeholder(__('الصندوق / نقدًا')),
                    Select::make('payment_method')->label(__('طريقة الدفع'))->options(\App\Enums\PaymentMethod::class)->default('transfer')->required(),
                ])
                ->action(function (array $data) {
                    /** @var EndOfServiceSettlement $s */
                    $s = $this->getRecord();
                    $voucher = app(VoucherService::class)->createPayment([
                        'party_type' => 'employee', 'party_id' => $s->employee_id, 'party_name' => $s->employee->full_name,
                        'amount' => (float) $s->net, 'payment_method' => $data['payment_method'], 'bank_account_id' => $data['bank_account_id'] ?? null,
                        'issued_at' => now(), 'reason' => __('تسوية نهاية خدمة — :name', ['name' => $s->employee->full_name]),
                    ]);
                    $s->forceFill(['status' => 'paid', 'payment_voucher_id' => $voucher->id])->save();
                    $s->employee->forceFill(['status' => 'terminated', 'termination_date' => $s->termination_date])->save();
                    Notification::make()->success()->title(__('صدر سند الصرف :n', ['n' => $voucher->number]))->send();
                    $this->redirect(EndOfServiceSettlementResource::getUrl('view', ['record' => $s]));
                }),
            Action::make('print')->label(__('طباعة'))->icon(Heroicon::OutlinedPrinter)->color('gray')
                ->url(fn () => route('documents.print', ['type' => 'end_of_service', 'id' => $this->getRecord()->getKey()]), shouldOpenInNewTab: true),
            DeleteAction::make()->visible(fn () => $this->getRecord()->status !== 'paid'),
        ];
    }
}
