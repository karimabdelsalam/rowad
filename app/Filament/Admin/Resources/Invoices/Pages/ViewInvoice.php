<?php

namespace App\Filament\Admin\Resources\Invoices\Pages;

use App\Exceptions\BusinessRuleException;
use App\Filament\Admin\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Services\Finance\InvoiceService;
use App\Services\Zatca\Phase2\ZatcaInvoicing;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ...\App\Filament\Support\DocumentActions::make('invoice'),
            Action::make('zatcaSubmit')
                ->label(__('إرسال إلى زاتكا'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('warning')
                ->visible(fn (Invoice $record) => in_array($record->zatca_status, ['pending', 'failed'], true) && filled($record->zatca_xml) && self::allowed('create'))
                ->action(function (Invoice $record) {
                    app(ZatcaInvoicing::class)->submit($record);
                    $record->refresh();
                    $label = __(ZatcaInvoicing::STATUSES[$record->zatca_status] ?? $record->zatca_status);
                    Notification::make()->title($label)
                        ->body(implode("\n", (array) $record->zatca_messages) ?: null)
                        ->{in_array($record->zatca_status, ['reported', 'cleared'], true) ? 'success' : 'danger'}()->send();
                }),
            Action::make('adjust')
                ->label(__('إشعار دائن / مدين'))
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('gray')
                ->visible(fn (Invoice $record) => $record->kind === 'invoice' && self::allowed('credit_note'))
                ->modalDescription(__('يصدر إشعارًا بنفس بنود الفاتورة ويُرسل إلى زاتكا؛ لا تُعدّل الفاتورة الأصلية أو تُحذف.'))
                ->schema([
                    Select::make('kind')->label(__('النوع'))->required()->default('credit')->native(false)
                        ->options(['credit' => __('إشعار دائن (إلغاء/تخفيض)'), 'debit' => __('إشعار مدين (زيادة)')]),
                    Textarea::make('reason')->label(__('السبب'))->required()->maxLength(500)->rows(2)
                        ->helperText(__('سبب الإصدار إلزامي في الفوترة الإلكترونية.')),
                ])
                ->action(function (Invoice $record, array $data) {
                    try {
                        $note = app(InvoiceService::class)->adjustment($record, $data['kind'], $data['reason']);
                    } catch (BusinessRuleException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();

                        return;
                    }
                    Notification::make()->title(__('تم إصدار الإشعار :number', ['number' => $note->number]))->success()->send();
                    $this->redirect(InvoiceResource::getUrl('view', ['record' => $note]));
                }),
            Action::make('xml')
                ->label(__('ملف XML'))
                ->icon(Heroicon::OutlinedCodeBracket)
                ->color('gray')
                ->visible(fn (Invoice $record) => filled($record->zatca_xml))
                ->action(fn (Invoice $record) => response()->streamDownload(
                    fn () => print ($record->zatca_xml),
                    $record->number.'.xml',
                    ['Content-Type' => 'application/xml'],
                )),
        ];
    }

    private static function allowed(string $ability): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->is_super_admin || $user->can("invoices.{$ability}"));
    }
}
