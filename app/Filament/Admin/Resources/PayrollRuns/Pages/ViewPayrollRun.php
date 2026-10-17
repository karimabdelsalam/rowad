<?php

namespace App\Filament\Admin\Resources\PayrollRuns\Pages;

use App\Enums\PayrollStatus;
use App\Exceptions\BusinessRuleException;
use App\Filament\Admin\Resources\Hr;
use App\Filament\Admin\Resources\PayrollRuns\PayrollRunResource;
use App\Models\BankAccount;
use App\Models\PayrollRun;
use App\Services\Hr\Payroll;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewPayrollRun extends ViewRecord
{
    protected static string $resource = PayrollRunResource::class;

    public function getTitle(): string
    {
        return __('رواتب :p', ['p' => $this->getRecord()->periodLabel()]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')->label(__('اعتماد المسيّر'))->icon(Heroicon::OutlinedCheckCircle)->color('success')->requiresConfirmation()
                ->visible(fn () => $this->getRecord()->status === PayrollStatus::Draft && Hr::can('payroll.approve'))
                ->modalDescription(__('بعد الاعتماد لا تُعدّل السطور. يمكن إعادة الفتح قبل الصرف.'))
                ->action(fn () => $this->run(fn () => app(Payroll::class)->approve($this->getRecord()), __('تم اعتماد المسيّر'))),
            Action::make('pay')->label(__('صرف الرواتب'))->icon(Heroicon::OutlinedBanknotes)->color('success')
                ->visible(fn () => $this->getRecord()->status === PayrollStatus::Approved && Hr::can('payroll.pay'))
                ->modalDescription(__('يصدر سند صرف لكل موظف بصافي راتبه، وتُخصم أقساط السلف.'))
                ->schema([
                    Select::make('bank_account_id')->label(__('من الحساب البنكي'))->options(fn () => BankAccount::query()->pluck('holder_name', 'id'))->placeholder(__('الصندوق / نقدًا')),
                    Select::make('payment_method')->label(__('طريقة الدفع'))->options(\App\Enums\PaymentMethod::class)->default('transfer')->required(),
                ])
                ->action(fn (array $data) => $this->run(function () use ($data) {
                    $n = app(Payroll::class)->pay($this->getRecord(), isset($data['bank_account_id']) ? BankAccount::find($data['bank_account_id']) : null, $data['payment_method']);

                    return trans_choice('{0} لا توجد رواتب للصرف|{1} صُرف راتب واحد|{2} صُرف راتبان|[3,*] صُرفت :count رواتب', $n);
                })),
            ActionGroup::make([
                Action::make('wps')->label(__('ملف حماية الأجور (WPS)'))->icon(Heroicon::OutlinedArrowDownTray)
                    ->visible(fn () => $this->getRecord()->status !== PayrollStatus::Draft && Hr::can('payroll.export'))
                    ->schema([
                        Select::make('bank_account_id')->label(__('حساب المنشأة'))->options(fn () => BankAccount::query()->pluck('holder_name', 'id'))
                            ->default(fn () => $this->getRecord()->bank_account_id ?? BankAccount::where('is_default', true)->value('id')),
                    ])
                    ->action(function (array $data) {
                        $run = $this->getRecord();
                        $content = app(Payroll::class)->wpsFile($run, isset($data['bank_account_id']) ? BankAccount::find($data['bank_account_id']) : null);

                        return response()->streamDownload(fn () => print($content), sprintf('WPS-%04d-%02d.csv', $run->year, $run->month), ['Content-Type' => 'text/csv']);
                    }),
                Action::make('print')->label(__('طباعة المسيّر'))->icon(Heroicon::OutlinedPrinter)
                    ->url(fn () => route('documents.print', ['type' => 'payroll', 'id' => $this->getRecord()->getKey()]), shouldOpenInNewTab: true),
                \App\Filament\Support\DocumentActions::pdf('payroll')->label(__('المسيّر PDF')),
                Action::make('reopen')->label(__('إعادة فتح للتعديل'))->icon(Heroicon::OutlinedLockOpen)->color('warning')->requiresConfirmation()
                    ->visible(fn () => $this->getRecord()->status === PayrollStatus::Approved && Hr::can('payroll.approve'))
                    ->action(fn () => $this->run(fn () => app(Payroll::class)->reopen($this->getRecord()), __('أُعيد فتح المسيّر'))),
                DeleteAction::make()->visible(fn () => $this->getRecord()->status === PayrollStatus::Draft),
            ]),
        ];
    }

    private function run(callable $callback, ?string $success = null): void
    {
        try {
            $result = $callback();
            Notification::make()->success()->title($success ?? (is_string($result) ? $result : __('تم')))->send();
            $this->refreshFormData([]);
            $this->redirect(PayrollRunResource::getUrl('view', ['record' => $this->getRecord()]));
        } catch (BusinessRuleException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }
    }
}
